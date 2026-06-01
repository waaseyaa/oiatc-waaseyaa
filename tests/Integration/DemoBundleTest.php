<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\AnokiiController;
use App\Controller\DemoController;
use App\Controller\HomeController;
use App\Provider\AppServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

final class DemoBundleTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
    }

    private function controller(): DemoController
    {
        return new DemoController(dirname(__DIR__, 2) . '/resources/demo/sheguiandah');
    }

    #[Test]
    public function the_demo_routes_are_registered_with_a_bare_path_redirect(): void
    {
        $router = new WaaseyaaRouter();
        new AppServiceProvider()->routes($router);

        self::assertSame('demo.sheguiandah', $router->match('/demo/sheguiandah/')['_route'] ?? null);
        self::assertSame('demo.sheguiandah.bare', $router->match('/demo/sheguiandah')['_route'] ?? null);
        self::assertSame('demo.sheguiandah.app-js', $router->match('/demo/sheguiandah/app.js')['_route'] ?? null);
        self::assertSame('demo.sheguiandah.logo', $router->match('/demo/sheguiandah/sheg-fn-logo.png')['_route'] ?? null);
    }

    #[Test]
    public function the_index_is_served_unlisted_with_the_disclaimer(): void
    {
        $response = $this->controller()->sheguiandahIndex();
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('noindex, nofollow', (string) $response->headers->get('X-Robots-Tag'));
        self::assertStringContainsString('<meta name="robots" content="noindex,nofollow" />', $html);
        self::assertStringContainsString('Illustrative prototype with sample data, prepared by OIATC. Not a live system.', $html);
    }

    #[Test]
    public function the_vault_reveals_no_real_architecture_or_credentials(): void
    {
        $html = (string) $this->controller()->sheguiandahIndex()->getContent();

        foreach (['WordPress', 'Operating Account', 'Payroll Service', 'Domain Registrar', 'Signing Certificate', 'admin@sheguiandahfn.ca', 'postmaster@sheguiandahfn.ca', 'Tr3aty!Huron', 'V@ult-9921', 'P@yr0ll-Secure'] as $leak) {
            self::assertStringNotContainsString($leak, $html, sprintf('Scrubbed term "%s" must not appear in the demo.', $leak));
        }

        // The vault still demonstrates the feature with obviously-fake placeholders.
        self::assertStringContainsString('Sample Service Account', $html);

        // The named individuals are intentionally retained.
        self::assertStringContainsString('Matthew Owl', $html);
    }

    #[Test]
    public function the_static_assets_are_served_with_their_content_types(): void
    {
        $appJs = $this->controller()->sheguiandahAppJs();
        self::assertSame(200, $appJs->getStatusCode());
        self::assertStringContainsString('application/javascript', (string) $appJs->headers->get('Content-Type'));
        self::assertNotSame('', (string) $appJs->getContent());

        $logo = $this->controller()->sheguiandahLogo();
        self::assertSame(200, $logo->getStatusCode());
        self::assertSame('image/png', (string) $logo->headers->get('Content-Type'));
        self::assertNotSame('', (string) $logo->getContent());
    }

    #[Test]
    public function the_demo_is_not_in_the_sitemap_or_any_nav(): void
    {
        $sitemap = (string) file_get_contents(dirname(__DIR__, 2) . '/public/sitemap.xml');
        self::assertStringNotContainsString('/demo/sheguiandah', $sitemap, 'Unlisted demo must not be in the sitemap.');

        $home = (string) new HomeController()->index()->getContent();
        self::assertStringNotContainsString('/demo/sheguiandah', $home, 'Home page must not link the demo.');

        $anokiiHome = (string) new AnokiiController()->home()->getContent();
        self::assertStringNotContainsString('/demo/sheguiandah', $anokiiHome, 'Anokii section must not link the demo.');

        $sagamok = (string) new AnokiiController()->sagamok()->getContent();
        self::assertStringNotContainsString('/demo/sheguiandah', $sagamok);
    }
}
