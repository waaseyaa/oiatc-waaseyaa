<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Serves self-contained static demo bundles verbatim (raw bytes, no Twig), from
 * outside the web root, so a clean trailing-slash URL works the same in dev and
 * in production regardless of the web server's directory-index behaviour.
 *
 * The only bundle today is the unlisted Sheguiandah clickable prototype under
 * /demo/sheguiandah/. It is intentionally not linked from any nav, home page,
 * the Anokii section, or sitemap.xml, and it is served noindex,nofollow (both a
 * meta tag in the HTML and an X-Robots-Tag header here), so it is reachable only
 * by direct link.
 */
final class DemoController
{
    public function __construct(private readonly string $bundleDir) {}

    public function sheguiandahIndex(): Response
    {
        return $this->serve('index.html', 'text/html; charset=UTF-8', noindex: true);
    }

    public function sheguiandahAppJs(): Response
    {
        return $this->serve('app.js', 'application/javascript; charset=UTF-8');
    }

    public function sheguiandahLogo(): Response
    {
        return $this->serve('sheg-fn-logo.png', 'image/png');
    }

    private function serve(string $name, string $contentType, bool $noindex = false): Response
    {
        $path = $this->bundleDir . '/' . $name;
        $body = is_file($path) ? file_get_contents($path) : false;
        if ($body === false) {
            return new Response('Not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $headers = ['Content-Type' => $contentType];
        if ($noindex) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }

        return new Response($body, 200, $headers);
    }
}
