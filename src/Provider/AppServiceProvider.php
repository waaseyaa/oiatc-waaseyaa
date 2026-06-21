<?php

declare(strict_types=1);

namespace App\Provider;

use Anokii\Config\DistributionConfig;
use App\Analytics\AnalyticsRecorder;
use App\Analytics\AnalyticsReport;
use App\Analytics\AnalyticsSchema;
use App\Analytics\SqliteChatQueryLog;
use App\Controller\AnalyticsDashboardController;
use App\Controller\AnokiiController;
use App\Controller\ChatController;
use App\Controller\CollectController;
use App\Controller\HomeController;
use App\Controller\PageStatsController;
use App\Support\ChatPromptBuilder;
use App\Support\GraphRetriever;
use App\Support\SqliteRateLimiter;
use App\Support\TopicVocabulary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\AI\Agent\Provider\AnthropicProvider;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AppServiceProvider extends ServiceProvider
{
    private ?DatabaseInterface $persistentDatabase = null;

    public function register(): void
    {
        // Anokii distribution posture: OIATC runs in the shared-graph tier.
        // Loaded from config/anokii.yaml so the install's tenancy mode and
        // module gate are resolvable from the container (lazy; a missing file
        // would resolve to the safe-by-default sovereign posture).
        $this->singleton(
            DistributionConfig::class,
            fn(): DistributionConfig => DistributionConfig::fromFile($this->projectRoot . '/config/anokii.yaml'),
        );
    }

    public function boot(): void
    {
        // Ensure the schema on the persistent file connection, NOT the ephemeral
        // one resolve(DatabaseInterface) hands back at boot — otherwise the table
        // is created on a connection that never reaches storage/waaseyaa.sqlite,
        // and the file-pinned analytics wiring in routes() finds no table. The
        // tryResolveDatabase() probe still gates this so routing-only unit tests
        // (no kernel) skip analytics entirely. See upstream note #018.
        if ($this->tryResolveDatabase() !== null) {
            new AnalyticsSchema($this->persistentDatabase())->ensure();
        }
    }

    /**
     * Resolve the database, returning null when no binding is available
     * (e.g. in unit tests that exercise routing without a kernel). This keeps
     * the analytics wiring optional so it never takes down the content pages.
     */
    private function tryResolveDatabase(): ?DatabaseInterface
    {
        try {
            $database = $this->resolve(DatabaseInterface::class);
        } catch (\Throwable) {
            return null;
        }

        return $database instanceof DatabaseInterface ? $database : null;
    }

    /**
     * The app's SQLite file path, mirroring the kernel: WAASEYAA_DB if set
     * (relative paths resolved against the project root), else the default
     * storage/waaseyaa.sqlite. Used to give the rate limiter a persistent
     * connection independent of the container.
     */
    private function databasePath(): string
    {
        $configured = getenv('WAASEYAA_DB') ?: '';
        if ($configured === '') {
            return $this->projectRoot . '/storage/waaseyaa.sqlite';
        }
        $isAbsolute = str_starts_with($configured, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $configured) === 1;

        return $isAbsolute ? $configured : $this->projectRoot . '/' . ltrim($configured, './');
    }

    /**
     * A DatabaseInterface pinned to the persistent SQLite file, memoised per
     * provider instance. resolve(DatabaseInterface) at route-build / boot time
     * can hand back an ephemeral connection (controllers are built once, not
     * per request), so writes wired to it never land in storage/waaseyaa.sqlite.
     * Everything that must persist — the rate limiter and analytics — shares
     * this file-backed connection instead. See upstream note #018.
     */
    private function persistentDatabase(): DatabaseInterface
    {
        return $this->persistentDatabase ??= DBALDatabase::createSqlite($this->databasePath());
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $controller = new HomeController();

        $router->addRoute(
            'home',
            RouteBuilder::create('/')
                ->controller(fn() => $controller->index())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'design-system',
            RouteBuilder::create('/design-system')
                ->controller(fn() => $controller->designSystem())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'about',
            RouteBuilder::create('/about')
                ->controller(fn() => $controller->about())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'support',
            RouteBuilder::create('/support')
                ->controller(fn() => $controller->support())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'programs',
            RouteBuilder::create('/programs')
                ->controller(fn() => $controller->programs())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // /programs/anishinaabemowin is consolidated into the canonical
        // /anishinaabemowin program home; 301 the old funder path to it.
        $router->addRoute(
            'programs.anishinaabemowin',
            RouteBuilder::create('/programs/anishinaabemowin')
                ->controller(fn() => new RedirectResponse('/anishinaabemowin', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'programs.anokii',
            RouteBuilder::create('/programs/anokii')
                ->controller(fn() => $controller->programAnokii())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'programs.community-knowledge',
            RouteBuilder::create('/programs/community-knowledge')
                ->controller(fn() => $controller->programCommunityKnowledge())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // The Transparency and member resources program was folded into /programs;
        // its Sagamok/RHT work now lives with the independent Transparency Circle
        // on rhtcircle.ca. 301 the program path to the programs index.
        $router->addRoute(
            'programs.member-resources',
            RouteBuilder::create('/programs/member-resources')
                ->controller(fn() => new RedirectResponse('/programs', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // Old /programs/transparency name (renamed 2026-06-14, then folded); 301
        // straight to the programs index so there is no redirect chain.
        $router->addRoute(
            'programs.transparency',
            RouteBuilder::create('/programs/transparency')
                ->controller(fn() => new RedirectResponse('/programs', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'positions.counter-disinformation',
            RouteBuilder::create('/positions/counter-disinformation')
                ->controller(fn() => $controller->counterDisinformationPosition())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'positions.prescribeit',
            RouteBuilder::create('/positions/prescribeit')
                ->controller(fn() => $controller->prescribeitPosition())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'positions.sovereign-ai',
            RouteBuilder::create('/positions/sovereign-ai')
                ->controller(fn() => $controller->sovereignAiPosition())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // RHT and Sagamok/Massey content moved to rhtcircle.ca (the Circle is now
        // the single source for that material and the petition data). These paths
        // 301 to their new homes; the old templates and corpus entries are removed.
        $router->addRoute(
            'explainers.robinson-huron-treaty',
            RouteBuilder::create('/explainers/robinson-huron-treaty')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/treaty-wide/the-treaty', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.robinson-huron-treaty.distribution-models',
            RouteBuilder::create('/explainers/robinson-huron-treaty/distribution-models')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/treaty-wide/distribution-models', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.massey-solar-project',
            RouteBuilder::create('/explainers/massey-solar-project')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/land/massey-solar-project', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.massey-solar-project.what-youve-heard',
            RouteBuilder::create('/explainers/massey-solar-project/what-youve-heard')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/land/massey-solar-project/what-youve-heard', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.massey-solar-project.voices',
            RouteBuilder::create('/explainers/massey-solar-project/voices')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/land/massey-solar-project/voices', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.massey-solar-project.climate-and-environment',
            RouteBuilder::create('/explainers/massey-solar-project/climate-and-environment')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/land/massey-solar-project/climate', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'practice.ai-in-coursework',
            RouteBuilder::create('/practice/ai-in-coursework')
                ->controller(fn() => $controller->practiceAiInCoursework())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anishinaabemowin',
            RouteBuilder::create('/anishinaabemowin')
                ->controller(fn() => $controller->anishinaabemowin())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anishinaabemowin.doll',
            RouteBuilder::create('/anishinaabemowin/doll')
                ->controller(fn() => $controller->anishinaabemowinDoll())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anishinaabemowin.doll.build',
            RouteBuilder::create('/anishinaabemowin/doll/build')
                ->controller(fn() => $controller->anishinaabemowinDollBuild())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anishinaabemowin.doll.process',
            RouteBuilder::create('/anishinaabemowin/doll/process')
                ->controller(fn() => $controller->anishinaabemowinDollProcess())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // The Anishinaabemowin work was first published as a practice case study;
        // it is now a top-level project section. 301 the old URL to the new home.
        $router->addRoute(
            'practice.anishinaabemowin-program',
            RouteBuilder::create('/practice/anishinaabemowin-program')
                ->controller(fn() => new RedirectResponse('/anishinaabemowin', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // The Sagamok members-website disclosure now lives on rhtcircle.ca; 301 there.
        $router->addRoute(
            'disclosure.sagamok-portal',
            RouteBuilder::create('/disclosure/sagamok-portal')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/communities/sagamok/members-website-issue', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // Anokii: shared relational-graph instance with per-community vantage lenses.
        $anokii = new AnokiiController();

        $router->addRoute(
            'anokii.home',
            RouteBuilder::create('/anokii')
                ->controller(fn() => $anokii->home())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anokii.sagamok',
            RouteBuilder::create('/anokii/sagamok')
                ->controller(fn() => $anokii->sagamok())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'anokii.massey',
            RouteBuilder::create('/anokii/massey')
                ->controller(fn() => $anokii->massey())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // The old Sagamok resources page now lives at the Anokii Sagamok lens.
        $router->addRoute(
            'resources.sagamok',
            RouteBuilder::create('/resources/sagamok')
                ->controller(fn() => new RedirectResponse('/anokii/sagamok', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.where-your-data-lives',
            RouteBuilder::create('/explainers/where-your-data-lives')
                ->controller(fn() => $controller->whereYourDataLives())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.how-sagamok-is-organized',
            RouteBuilder::create('/explainers/how-sagamok-is-organized')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/communities/sagamok/how-its-organized', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // The records request and its full-letter page now live on rhtcircle.ca,
        // where the campaign and its signature data are hosted. Both 301 there.
        $router->addRoute(
            'support.records-request',
            RouteBuilder::create('/support/records-request')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/standard/records-request', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'support.records-request-letter',
            RouteBuilder::create('/support/records-request-letter')
                ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/standard/records-request', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // Unlisted static demo bundle (Sheguiandah clickable prototype). Served
        // verbatim from resources/ via DemoController, noindex,nofollow, not in
        // the sitemap, and not linked from any nav. Reachable only by direct link
        // at /demo/sheguiandah/ (the bare path 301s to the trailing slash so the
        // bundle's relative asset references resolve).
        $demo = new \App\Controller\DemoController($this->projectRoot . '/resources/demo/sheguiandah');

        $router->addRoute(
            'demo.sheguiandah',
            RouteBuilder::create('/demo/sheguiandah/')
                ->controller(fn() => $demo->sheguiandahIndex())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'demo.sheguiandah.bare',
            RouteBuilder::create('/demo/sheguiandah')
                ->controller(fn() => new RedirectResponse('/demo/sheguiandah/', 301))
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'demo.sheguiandah.app-js',
            RouteBuilder::create('/demo/sheguiandah/app.js')
                ->controller(fn() => $demo->sheguiandahAppJs())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'demo.sheguiandah.logo',
            RouteBuilder::create('/demo/sheguiandah/sheg-fn-logo.png')
                ->controller(fn() => $demo->sheguiandahLogo())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        // The petition admin was retired with the petition feature. Without its
        // route, /admin/petitions fell through to the framework admin-SPA
        // catch-all (/admin/{path}, priority 0), which served an empty shell
        // there. Shadow that exact path with a 301 to the admin root so no
        // orphaned shell remains. priority(10) wins over the catch-all.
        $router->addRoute(
            'admin.petitions.retired',
            RouteBuilder::create('/admin/petitions')
                ->controller(fn() => new RedirectResponse('/admin', 301))
                ->allowAll()
                ->methods('GET')
                ->priority(10)
                ->build(),
        );

        if ($this->tryResolveDatabase() !== null) {
            // Pin analytics to the persistent SQLite file. resolve(DatabaseInterface)
            // here returns an ephemeral connection (the route/controller closure is
            // built once, not per request), so beacon writes wired to it never reach
            // storage/waaseyaa.sqlite and the dashboard reads an empty ephemeral DB —
            // verified: POST /api/collect returned 204 but persisted 0 rows. The
            // tryResolveDatabase() probe stays only as a "kernel present?" gate so
            // routing-only unit tests skip analytics. Same fix as the chat limiter;
            // see upstream note #018.
            $database = $this->persistentDatabase();
            $secret = getenv('WAASEYAA_ANALYTICS_SECRET')
                ?: (getenv('WAASEYAA_JWT_SECRET') ?: 'oiatc-analytics');
            $report = new AnalyticsReport($database);
            $collect = new CollectController(new AnalyticsRecorder($database, $secret));
            $analytics = new AnalyticsDashboardController($report);
            $pageStats = new PageStatsController($report);

            $router->addRoute(
                'analytics.collect',
                RouteBuilder::create('/api/collect')
                    ->controller(fn(Request $request) => $collect->collect($request))
                    ->allowAll()
                    ->methods('POST')
                    ->build(),
            );

            $router->addRoute(
                'admin.analytics',
                // Public (no Caddy basic_auth currently on /admin/*). priority(10) is
                // required so this exact route wins over admin-surface's `admin_spa`
                // catch-all (`/admin/{path}`, priority 0), which otherwise serves its
                // bundled SPA here and shadows the dashboard.
                RouteBuilder::create('/admin/analytics')
                    ->controller(fn(Request $request) => $analytics->index($request))
                    ->allowAll()
                    ->methods('GET')
                    ->priority(10)
                    ->build(),
            );

            $router->addRoute(
                'analytics.page-stats',
                RouteBuilder::create('/api/page-stats')
                    ->controller(fn(Request $request) => $pageStats->stats($request))
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );
        }

        if ($entityTypeManager !== null) {
            $news = new \App\Controller\NewsController($entityTypeManager->getRepository('news_post'));

            $router->addRoute(
                'news.index',
                RouteBuilder::create('/news')
                    ->controller(fn(Request $request) => $news->index($request))
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );

            // Literal feed route registered before /news/{slug} so the param can't swallow it.
            $router->addRoute(
                'news.rss',
                RouteBuilder::create('/news/rss.xml')
                    ->controller(fn() => $news->rss())
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );

            // The Sagamok members-website disclosure post was retired; its content
            // lives on rhtcircle.ca. Literal route before /news/{slug} so the param
            // cannot swallow it.
            $router->addRoute(
                'news.sagamok-portal-disclosure.redirect',
                RouteBuilder::create('/news/sagamok-portal-disclosure')
                    ->controller(fn() => new RedirectResponse('https://rhtcircle.ca/communities/sagamok/members-website-issue', 301))
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );

            // News posts retired with the RHT/Sagamok migration and the program
            // fold. Each 301s to the subject's live home. Literal routes before
            // /news/{slug} so the param cannot swallow them.
            $retiredNews = [
                'massey-solar-ieso-contract-awarded' => 'https://rhtcircle.ca/land/massey-solar-project',
                'potentia-responds-massey' => 'https://rhtcircle.ca/land/massey-solar-project',
                'massey-solar-open-houses-paused' => 'https://rhtcircle.ca/land/massey-solar-project',
                'massey-solar-drop-in-sessions-fire-hall' => 'https://rhtcircle.ca/land/massey-solar-project',
                'robinson-huron-treaty-explainer' => 'https://rhtcircle.ca/treaty-wide/the-treaty',
                'add-your-voice' => 'https://rhtcircle.ca/standard/records-request',
                'site-programs-restructure' => '/programs',
            ];
            foreach ($retiredNews as $slug => $target) {
                $router->addRoute(
                    'news.retired.' . $slug,
                    RouteBuilder::create('/news/' . $slug)
                        ->controller(fn() => new RedirectResponse($target, 301))
                        ->allowAll()
                        ->methods('GET')
                        ->build(),
                );
            }

            $router->addRoute(
                'news.post',
                RouteBuilder::create('/news/{slug}')
                    ->controller(fn(Request $request, string $slug) => $news->show($slug))
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );

            $router->addRoute(
                'news.explainer-updates',
                RouteBuilder::create('/api/explainer-updates')
                    ->controller(fn(Request $request) => $news->explainerUpdates($request))
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );

            // Grounded RAG chat over the doc_chunk knowledge base (Path B).
            // JSON request body -> CSRF auto-skipped; rate-limited per client.
            // Pin the limiter to the persistent SQLite file (shared helper).
            // resolve(DatabaseInterface) at route-registration time can hand back
            // an ephemeral connection (the route/controller is built once, not per
            // request), which would make the rate limit reset every request. See
            // upstream note #018.
            // Construct the chat provider directly from the env key rather than
            // resolve(ProviderInterface): at route-build time the container hands
            // back the framework's NullLlmProvider default, not our binding (same
            // build-once/ephemeral issue as the DB — see upstream #018).
            $anthropicKey = getenv('ANTHROPIC_API_KEY') ?: '';
            // Web research is opt-in per instance (ANOKII_WEB_RESEARCH=1) and only
            // takes effect once a real provider is configured. Off by default so
            // the closed-corpus, refuse-rather-than-reach-out behavior is the
            // baseline until Russell deliberately turns it on.
            $webResearch = (getenv('ANOKII_WEB_RESEARCH') ?: '') === '1' && $anthropicKey !== '';
            $chat = new ChatController(
                retriever: new GraphRetriever($this->persistentDatabase()),
                prompts: new ChatPromptBuilder(),
                provider: $anthropicKey !== ''
                    ? new AnthropicProvider($anthropicKey, AiServiceProvider::MODEL)
                    : $this->resolve(ProviderInterface::class),
                limiter: new SqliteRateLimiter($this->persistentDatabase()),
                logger: $this->resolve(LoggerInterface::class),
                queryLog: new SqliteChatQueryLog($this->persistentDatabase()),
                topics: new TopicVocabulary(),
                configured: $anthropicKey !== '',
                webResearch: $webResearch,
            );
            $router->addRoute(
                'chat',
                RouteBuilder::create('/api/chat')
                    ->controller(fn(Request $request) => $chat->handle($request))
                    ->allowAll()
                    ->methods('POST')
                    ->build(),
            );
        }

        // '/about' is a real page (see the 'about' route above), so it is not a
        // legacy redirect; the rest redirect to home.
        $legacyPaths = ['/waaseyaa', '/minoo', '/grants', '/contact', '/founding-charter'];
        foreach ($legacyPaths as $legacyPath) {
            $router->addRoute(
                'legacy.redirect' . str_replace('/', '.', $legacyPath),
                RouteBuilder::create($legacyPath)
                    ->controller(fn() => $controller->redirectToHome())
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );
        }
    }
}
