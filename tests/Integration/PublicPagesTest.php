<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\AnokiiController;
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
        new AppServiceProvider()->routes($router);

        $this->assertSame('home', $router->match('/')['_route'] ?? null);
        $this->assertSame('design-system', $router->match('/design-system')['_route'] ?? null);

        foreach (['/about', '/waaseyaa', '/minoo', '/grants', '/contact', '/founding-charter'] as $legacy) {
            $match = $router->match($legacy);
            $this->assertArrayHasKey('_route', $match, sprintf('Expected %s to resolve to a legacy redirect route.', $legacy));
            $this->assertStringStartsWith('legacy.redirect', $match['_route'] ?? '');
        }
    }

    #[Test]
    public function homepage_renders_council_identity_and_pillars(): void
    {
        $response = new HomeController()->index();
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
        $response = new HomeController()->designSystem();
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
        $response = new HomeController()->redirectToHome();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/', $response->getTargetUrl());
    }

    #[Test]
    public function anokii_routes_are_registered_and_old_resources_url_redirects(): void
    {
        $router = new WaaseyaaRouter();
        new AppServiceProvider()->routes($router);

        $this->assertSame('anokii.home', $router->match('/anokii')['_route'] ?? null);
        $this->assertSame('anokii.sagamok', $router->match('/anokii/sagamok')['_route'] ?? null);
        $this->assertSame('anokii.massey', $router->match('/anokii/massey')['_route'] ?? null);
        // The old resources URL is still routed (now a 301 redirect to the lens).
        $this->assertSame('resources.sagamok', $router->match('/resources/sagamok')['_route'] ?? null);
    }

    #[Test]
    public function sagamok_lens_renders_tabs_search_and_corrected_content(): void
    {
        $response = new AnokiiController()->sagamok();
        $html = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Sagamok member resources', $html);

        // Tabbed + search interaction is present.
        $this->assertStringContainsString('id="r-q"', $html, 'search input');
        $this->assertStringContainsString('data-panel="nav"', $html);
        $this->assertStringContainsString('data-panel="prog"', $html);
        $this->assertStringContainsString('data-panel="council"', $html);
        $this->assertStringContainsString('data-go="nav"', $html, 'programs -> navigator cross-link');

        // Independence note and official Sagamok links retained.
        $this->assertStringContainsString('Independent of Sagamok Chief and Council', $html);
        $this->assertStringContainsString('sagamokanishnawbek.com/meeting-minutes', $html);
        $this->assertStringContainsString('koognaasewin.com', $html);

        // Member transactions point at the band (Membership/Finance), not the online forms.
        $this->assertStringContainsString('rather than relying on the online forms', $html);

        // Content corrections from the port brief.
        $this->assertStringContainsString('705-501-8950', $html, 'Lifelong Learning Centre direct line');
        $this->assertStringNotContainsString('ISETS', $html, 'no specific ISETS program name asserted');
        $this->assertStringContainsString('Nogdawindamin', $html, 'CFAU framed as a prevention service re Nogdawindamin');
        $this->assertStringContainsString('reclaim jurisdiction over child welfare', $html, 'Koognaasewin reframed as a North Shore initiative');
        $this->assertStringContainsString('not a Sagamok service you apply to', $html);

        // Rendered through Twig; no raw template tags leaked.
        $this->assertStringNotContainsString('{%', $html);
    }

    #[Test]
    public function each_lens_renders_its_communitys_suggested_prompts(): void
    {
        $sagamokPrompts = [
            'How do I apply for housing?',
            'I want to start a business',
            'Where can I get mental health support?',
            'I need to see a doctor, where do I go?',
            'How do I apply for Ontario Works?',
            'How do I bring something to Council?',
        ];
        $masseyPrompts = [
            'What is the Massey Solar Project?',
            'Will the solar project help with climate change?',
            'What about the farmland, water, and wildlife?',
            'What happens to the panels at the end of their life?',
            'Where is the project in the approval process?',
            'How does the project relate to Sagamok?',
        ];

        $sagamok = (string) new AnokiiController()->sagamok()->getContent();
        $this->assertStringContainsString('class="r-ask__pill"', $sagamok, 'Pills render on the Sagamok lens.');
        foreach ($sagamokPrompts as $prompt) {
            $this->assertStringContainsString('data-q="' . $prompt . '"', $sagamok, sprintf('Sagamok lens shows: %s', $prompt));
        }
        foreach ($masseyPrompts as $prompt) {
            $this->assertStringNotContainsString('data-q="' . $prompt . '"', $sagamok, 'Sagamok lens must not show Massey pills.');
        }

        $massey = (string) new AnokiiController()->massey()->getContent();
        $this->assertStringContainsString('class="r-ask__pill"', $massey, 'Pills render on the Massey lens.');
        foreach ($masseyPrompts as $prompt) {
            $this->assertStringContainsString('data-q="' . $prompt . '"', $massey, sprintf('Massey lens shows: %s', $prompt));
        }
        foreach ($sagamokPrompts as $prompt) {
            $this->assertStringNotContainsString('data-q="' . $prompt . '"', $massey, 'Massey lens must not show Sagamok pills.');
        }
    }

    #[Test]
    public function massey_resources_module_lists_the_full_explainer_cluster(): void
    {
        $massey = (string) new AnokiiController()->massey()->getContent();

        // All four cluster links, including the climate and environment companion.
        $this->assertStringContainsString('href="/explainers/massey-solar-project">The Massey Solar Project in 2026, a guide</a>', $massey);
        $this->assertStringContainsString('href="/explainers/massey-solar-project-what-youve-heard">What you\'ve heard, checked against the record</a>', $massey);
        $this->assertStringContainsString('href="/explainers/massey-solar-project-voices">Voices from the community</a>', $massey);
        $this->assertStringContainsString('href="/explainers/massey-solar-project/climate-and-environment">The climate and environment context</a>', $massey);
    }

    #[Test]
    public function data_sovereignty_explainer_route_is_registered(): void
    {
        $router = new WaaseyaaRouter();
        new AppServiceProvider()->routes($router);

        $this->assertSame('explainers.where-your-data-lives', $router->match('/explainers/where-your-data-lives')['_route'] ?? null);
    }

    #[Test]
    public function data_sovereignty_explainer_renders_visuals_notes_and_sources(): void
    {
        $response = new HomeController()->whereYourDataLives();
        $html = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString("Where does your community's data actually live?", $html);

        // Both custom visuals are present: the redacted CDN-link anatomy and the SVG map.
        $this->assertStringContainsString('cdn.prod.website-files.com', $html);
        $this->assertStringContainsString('class="dsv-url"', $html);
        $this->assertStringContainsString('class="dsv-map"', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('Northern Virginia', $html);

        // Not-legal-advice and not-affiliated notes retained.
        $this->assertStringContainsString('is not legal advice', $html);
        $this->assertStringContainsString('Independent of Sagamok Chief and Council', $html);

        // Sources retained (CLOUD Act, OCAP/FNIGC) and the reciprocal link to the disclosure.
        $this->assertStringContainsString('CLOUD Act', $html);
        $this->assertStringContainsString('fnigc.ca', $html);
        $this->assertStringContainsString('/disclosure/sagamok-portal', $html);

        $this->assertStringNotContainsString('{%', $html);
    }

    #[Test]
    public function disclosure_links_back_to_the_data_sovereignty_explainer(): void
    {
        $html = (string) new HomeController()->sagamokPortalDisclosure()->getContent();

        $this->assertStringContainsString('/explainers/where-your-data-lives', $html, 'Disclosure must link to the companion explainer.');
    }

    #[Test]
    public function massey_climate_companion_route_is_registered_and_linked_from_the_main_explainer(): void
    {
        $router = new WaaseyaaRouter();
        new AppServiceProvider()->routes($router);

        $this->assertSame(
            'explainers.massey-solar-project.climate-and-environment',
            $router->match('/explainers/massey-solar-project/climate-and-environment')['_route'] ?? null,
        );

        // The main explainer carries the companion nav card and the one-line pointer.
        $main = (string) new HomeController()->masseySolarProjectExplainer()->getContent();
        $this->assertStringContainsString('/explainers/massey-solar-project/climate-and-environment', $main);
    }

    #[Test]
    public function massey_climate_companion_renders_neutral_sourced_content_indexable_and_without_em_dashes(): void
    {
        $response = new HomeController()->masseySolarProjectClimateAndEnvironment();
        $html = (string) $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Climate and environment context', $html);
        // Public and indexable (unlike the unlisted demo).
        $this->assertStringContainsString('content="index, follow"', $html);
        // Neutral framing and sourcing retained.
        $this->assertStringContainsString('does not take a position', $html);
        $this->assertStringContainsString('Renewable Energy Approval', $html);
        $this->assertStringContainsString('ieso.ca', $html);
        // In-text references to the main explainer link to it.
        $this->assertStringContainsString('href="/explainers/massey-solar-project"', $html);
        // Standard cluster byline/footer.
        $this->assertStringContainsString('Chi-miigwech for reading.', $html);
        // No em dashes anywhere on this page.
        $this->assertStringNotContainsString("\u{2014}", $html, 'No em dashes on the climate companion.');
        $this->assertStringNotContainsString('{%', $html, 'No raw Twig tags leaked.');
    }
}
