<?php

declare(strict_types=1);

namespace App\Provider;

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
use App\Controller\PetitionAdminController;
use App\Controller\PetitionController;
use App\Petition\PetitionAdminAuth;
use App\Petition\PetitionRepository;
use App\Petition\PetitionSchema;
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

    public function register(): void {}

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

            // Petition tables + the seed campaign. Idempotent: the schema is
            // guarded by tableExists() and ensureCampaign() only inserts when
            // the slug is absent. Storage is OIATC's own SQLite on the storage
            // volume (sovereign at rest); see PetitionSchema for the OCAP note.
            (new PetitionSchema($this->persistentDatabase()))->ensure();
            $this->petitionRepository()->ensureCampaign(
                'sagamok-data-governance',
                'Member data governance at Sagamok',
                'Ask Sagamok Chief and Council to take up member data governance: acknowledge the exposure, notify members, and move our data onto infrastructure we control.',
                'Sagamok Chief and Council',
            );
            $this->petitionRepository()->ensureCampaign(
                'records-request-support',
                'Support the member records request',
                'We, the undersigned members of Sagamok Anishnawbek, support the records request submitted to Chief and Council. We want clear answers, on the record, to one question: when the Nation invests in businesses and ventures, what are the benefits to the membership, and who is being served? We ask Council to provide the records and respond to the membership.',
                'Sagamok Chief and Council',
            );
        }
    }

    private ?PetitionRepository $petitionRepository = null;

    /**
     * The petition repository, pinned to the persistent SQLite file (same
     * reasoning as analytics/rate-limiter: resolve(DatabaseInterface) at
     * boot/route-build can hand back an ephemeral connection). The hash secret
     * salts the rate-limit-only IP/UA hashes.
     */
    private function petitionRepository(): PetitionRepository
    {
        return $this->petitionRepository ??= new PetitionRepository(
            $this->persistentDatabase(),
            getenv('WAASEYAA_PETITION_SECRET') ?: (getenv('WAASEYAA_JWT_SECRET') ?: 'oiatc-petition'),
        );
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

        $router->addRoute(
            'programs.anishinaabemowin',
            RouteBuilder::create('/programs/anishinaabemowin')
                ->controller(fn() => $controller->programAnishinaabemowin())
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

        $router->addRoute(
            'programs.transparency',
            RouteBuilder::create('/programs/transparency')
                ->controller(fn() => $controller->programTransparency())
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
            'explainers.robinson-huron-treaty',
            RouteBuilder::create('/explainers/robinson-huron-treaty')
                ->controller(fn() => $controller->robinsonHuronTreatyExplainer())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.robinson-huron-treaty.distribution-models',
            RouteBuilder::create('/explainers/robinson-huron-treaty/distribution-models')
                ->controller(fn() => $controller->robinsonHuronTreatyDistributionModels())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.massey-solar-project',
            RouteBuilder::create('/explainers/massey-solar-project')
                ->controller(fn() => $controller->masseySolarProjectExplainer())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.massey-solar-project.what-youve-heard',
            RouteBuilder::create('/explainers/massey-solar-project/what-youve-heard')
                ->controller(fn() => $controller->masseySolarProjectWhatYouveHeard())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.massey-solar-project.voices',
            RouteBuilder::create('/explainers/massey-solar-project/voices')
                ->controller(fn() => $controller->masseySolarProjectVoices())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'explainers.massey-solar-project.climate-and-environment',
            RouteBuilder::create('/explainers/massey-solar-project/climate-and-environment')
                ->controller(fn() => $controller->masseySolarProjectClimateAndEnvironment())
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

        $router->addRoute(
            'disclosure.sagamok-portal',
            RouteBuilder::create('/disclosure/sagamok-portal')
                ->controller(fn() => $controller->sagamokPortalDisclosure())
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
                ->controller(fn() => $controller->howSagamokIsOrganized())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'support.records-request',
            RouteBuilder::create('/support/records-request')
                ->controller(fn() => $controller->recordsRequestSupport())
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'support.records-request-letter',
            RouteBuilder::create('/support/records-request-letter')
                ->controller(fn() => $controller->recordsRequestLetter())
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

            // Petition / "Add your voice". Public sign + live count + remove +
            // privacy; authenticated admin (list, CSV export, create/deactivate).
            // All signature data stays in OIATC's own database.
            $petitions = $this->petitionRepository();
            $petition = new PetitionController($petitions);
            $petitionAdmin = new PetitionAdminController($petitions, PetitionAdminAuth::fromEnv());

            // Sign takes a JSON body (CSRF auto-skipped, like chat/collect).
            $router->addRoute(
                'petition.sign',
                RouteBuilder::create('/api/petition/sign')
                    ->controller(fn(Request $request) => $petition->sign($request))
                    ->allowAll()
                    ->methods('POST')
                    ->build(),
            );

            $router->addRoute(
                'petition.info',
                RouteBuilder::create('/api/petition/{slug}')
                    ->controller(fn(Request $request, string $slug) => $petition->info($slug))
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );

            $router->addRoute(
                'petition.remove',
                RouteBuilder::create('/petition/remove/{token}')
                    ->controller(fn(Request $request, string $token) => $petition->remove($token))
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );

            $router->addRoute(
                'petition.privacy',
                RouteBuilder::create('/petition/privacy')
                    ->controller(fn() => $petition->privacy())
                    ->allowAll()
                    ->methods('GET')
                    ->build(),
            );

            // /admin/* currently has no edge auth and an admin_spa catch-all at
            // priority 0; priority(10) wins here and PetitionAdminAuth (HTTP
            // Basic, fails closed) gates every petition-admin action.
            $router->addRoute(
                'petition.admin',
                RouteBuilder::create('/admin/petitions')
                    ->controller(fn(Request $request) => $request->isMethod('POST')
                        ? $petitionAdmin->create($request)
                        : $petitionAdmin->index($request))
                    ->allowAll()
                    ->methods('GET', 'POST')
                    ->priority(10)
                    ->build(),
            );

            $router->addRoute(
                'petition.admin.active',
                RouteBuilder::create('/admin/petitions/{slug}/active')
                    ->controller(fn(Request $request, string $slug) => $petitionAdmin->setActive($request, $slug))
                    ->allowAll()
                    ->methods('POST')
                    ->priority(10)
                    ->build(),
            );

            $router->addRoute(
                'petition.admin.export',
                RouteBuilder::create('/admin/petitions/{slug}/export.csv')
                    ->controller(fn(Request $request, string $slug) => $petitionAdmin->export($request, $slug))
                    ->allowAll()
                    ->methods('GET')
                    ->priority(10)
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

        $legacyPaths = ['/about', '/waaseyaa', '/minoo', '/grants', '/contact', '/founding-charter'];
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
