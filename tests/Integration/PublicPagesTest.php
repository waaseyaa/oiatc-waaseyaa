<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\PageController;
use App\Provider\SiteServiceProvider;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Waaseyaa\Routing\WaaseyaaRouter;

final class PublicPagesTest extends TestCase
{
    private function createController(): PageController
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));

        return new PageController($twig);
    }

    private function createXPath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xpath;
    }

    #[Test]
    public function site_service_provider_registers_all_public_routes(): void
    {
        $router = new WaaseyaaRouter();
        (new SiteServiceProvider())->routes($router);

        $expectedRoutes = [
            '/' => 'page.home',
            '/about' => 'page.about',
            '/waaseyaa' => 'page.waaseyaa',
            '/minoo' => 'page.minoo',
            '/grants' => 'page.grants',
            '/founding-charter' => 'page.charter',
            '/contact' => 'page.contact',
        ];

        foreach ($expectedRoutes as $path => $routeName) {
            $params = $router->match($path);
            $this->assertSame($routeName, $params['_route'] ?? null, sprintf('Expected %s route for %s.', $routeName, $path));
        }
    }

    #[Test]
    public function homepage_renders_sovereignty_and_platform_sections(): void
    {
        $response = $this->createController()->home([], [], null, Request::create('/'));
        $xpath = $this->createXPath($response->content);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Indigenous digital sovereignty in Ontario', $response->content);
        $this->assertStringContainsString('Waaseyaa', $response->content);
        $this->assertStringContainsString('Minoo', $response->content);
        $this->assertGreaterThanOrEqual(1, (int) $xpath->evaluate("count(//a[@href='/grants'])"));
        $this->assertGreaterThanOrEqual(1, (int) $xpath->evaluate("count(//a[@href='/founding-charter'])"));
    }

    #[Test]
    public function secondary_pages_render_key_headings_and_contact_links(): void
    {
        $controller = $this->createController();

        $pageExpectations = [
            ['/about', 'About OIATC'],
            ['/waaseyaa', 'Waaseyaa'],
            ['/minoo', 'Minoo'],
            ['/grants', 'Grants & Funding'],
            ['/founding-charter', 'Founding Charter'],
            ['/contact', 'Contact / Partner'],
        ];

        foreach ($pageExpectations as [$path, $heading]) {
            $method = match ($path) {
                '/about' => 'about',
                '/waaseyaa' => 'waaseyaa',
                '/minoo' => 'minoo',
                '/grants' => 'grants',
                '/founding-charter' => 'charter',
                '/contact' => 'contact',
            };

            $response = $controller->{$method}([], [], null, Request::create($path));
            $this->assertSame(200, $response->statusCode, sprintf('%s should return 200.', $path));
            $this->assertStringContainsString($heading, $response->content);
        }

        $contactResponse = $controller->contact([], [], null, Request::create('/contact'));
        $contactXpath = $this->createXPath($contactResponse->content);

        $this->assertSame(4, (int) $contactXpath->evaluate("count(//a[starts-with(@href, 'mailto:')])"));
    }

    #[Test]
    public function grants_page_includes_freshness_marker_and_sources(): void
    {
        $response = $this->createController()->grants([], [], null, Request::create('/grants'));
        $xpath = $this->createXPath($response->content);

        $this->assertSame(200, $response->statusCode);
        $this->assertStringContainsString('Last reviewed', $response->content);
        $this->assertStringContainsString('FedDev Ontario', $response->content);
        $this->assertGreaterThanOrEqual(3, (int) $xpath->evaluate("count(//a[starts-with(@href, 'https://')])"));
    }
}
