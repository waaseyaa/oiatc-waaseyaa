<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\HomeController;
use App\Provider\AppServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\SSR\SsrServiceProvider;

final class PublicPagesTest extends TestCase
{
    /**
     * Boot the SSR Twig environment once for the suite, the same way the
     * kernel does (setKernelContext + boot), so the static-page controllers
     * can render their templates/ files. Mirrors how the app runs.
     */
    public static function setUpBeforeClass(): void
    {
        $provider = new SsrServiceProvider();
        $provider->setKernelContext(dirname(__DIR__, 2), [], []);
        $provider->boot();
    }

    #[Test]
    public function app_service_provider_registers_home_and_design_system_and_legacy_redirects(): void
    {
        $router = new WaaseyaaRouter();
        (new AppServiceProvider())->routes($router);

        $this->assertSame('home', $router->match('/')['_route'] ?? null);
        $this->assertSame('design-system', $router->match('/design-system')['_route'] ?? null);

        foreach (['/about', '/waaseyaa', '/minoo', '/grants', '/contact', '/founding-charter'] as $legacy) {
            $match = $router->match($legacy);
            $this->assertNotNull($match, sprintf('Expected %s to resolve to a legacy redirect route.', $legacy));
            $this->assertStringStartsWith('legacy.redirect', $match['_route'] ?? '');
        }
    }

    #[Test]
    public function homepage_renders_council_identity_and_pillars(): void
    {
        $response = (new HomeController())->index();
        $html = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Ontario Indigenous AI', $html);
        $this->assertStringContainsString('A council of two', $html);
        $this->assertStringContainsString('Russell Jones', $html);
        $this->assertStringContainsString('Sagamok Anishnawbek', $html);
        $this->assertStringContainsString('Waaseyaa', $html);
        $this->assertStringContainsString('Minoo', $html);
        $this->assertStringContainsString('Web Networks', $html);
        $this->assertStringContainsString('jonesrussell42@gmail.com', $html);
    }

    #[Test]
    public function design_system_page_renders_all_ten_sections(): void
    {
        $response = (new HomeController())->designSystem();
        $html = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Council design system', $html);

        foreach (['principles', 'color', 'type', 'space', 'motion', 'components', 'patterns', 'icons', 'voice', 'a11y'] as $sectionId) {
            $this->assertStringContainsString(sprintf('id="%s"', $sectionId), $html, sprintf('Design system should render #%s section.', $sectionId));
        }

        // Regression guard for the file_get_contents pipeline: Twig must execute,
        // not leak template source. The {% for %} grid loop rendered 12 cells.
        $this->assertStringNotContainsString('{%', $html, 'Raw Twig tags must not leak into the rendered page.');
        $this->assertSame(12, substr_count($html, 'height: 56px'), 'The grid demo loop should render 12 cells.');
    }

    #[Test]
    public function legacy_paths_redirect_to_home_with_301(): void
    {
        $response = (new HomeController())->redirectToHome();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/', $response->getTargetUrl());
    }
}
