<?php

declare(strict_types=1);

namespace App\Provider;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\AnalyticsReport;
use App\Analytics\AnalyticsSchema;
use App\Controller\AnalyticsDashboardController;
use App\Controller\ChatController;
use App\Controller\CollectController;
use App\Controller\HomeController;
use App\Controller\PageStatsController;
use App\Support\ChatPromptBuilder;
use App\Support\KnowledgeRetriever;
use App\Support\SqliteRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $database = $this->tryResolveDatabase();
        if ($database !== null) {
            new AnalyticsSchema($database)->ensure();
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
            'positions.counter-disinformation',
            RouteBuilder::create('/positions/counter-disinformation')
                ->controller(fn() => $controller->counterDisinformationPosition())
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
            'practice.ai-in-coursework',
            RouteBuilder::create('/practice/ai-in-coursework')
                ->controller(fn() => $controller->practiceAiInCoursework())
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

        $router->addRoute(
            'resources.sagamok',
            RouteBuilder::create('/resources/sagamok')
                ->controller(fn() => $controller->sagamokResources())
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

        $database = $this->tryResolveDatabase();
        if ($database !== null) {
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
            // Pin the limiter to the persistent SQLite file. resolve(DatabaseInterface)
            // at route-registration time can hand back an ephemeral connection
            // (the route/controller is built once, not per request), which would
            // make the rate limit reset every request. See upstream note #018.
            $chat = new ChatController(
                retriever: new KnowledgeRetriever($entityTypeManager->getRepository('doc_chunk')),
                prompts: new ChatPromptBuilder(),
                provider: $this->resolve(ProviderInterface::class),
                limiter: new SqliteRateLimiter(DBALDatabase::createSqlite($this->databasePath())),
                logger: $this->resolve(LoggerInterface::class),
                configured: (getenv('ANTHROPIC_API_KEY') ?: '') !== '',
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
