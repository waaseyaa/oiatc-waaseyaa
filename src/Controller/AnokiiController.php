<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * The Anokii instance: one shared relational graph rendered from per-community
 * vantage points. `/anokii` is the instance home (communities + module legend);
 * `/anokii/sagamok` and `/anokii/massey` are the vantage lenses. A community is a
 * point of view onto the graph, not a walled tenant, so the lenses share one
 * shell and one chat endpoint, passing only their vantage community slug.
 */
final class AnokiiController
{
    /**
     * Vantage communities. The chat sends `slug` as the /api/chat community
     * parameter; `name` is the display noun.
     *
     * @var array<string, array{name: string}>
     */
    private const COMMUNITIES = [
        'sagamok' => ['name' => 'Sagamok'],
        'massey' => ['name' => 'Massey'],
    ];

    public function home(): Response
    {
        return $this->render('anokii/home.html.twig', [
            'communities' => self::COMMUNITIES,
        ]);
    }

    public function sagamok(): Response
    {
        return $this->lens('sagamok');
    }

    public function massey(): Response
    {
        return $this->lens('massey');
    }

    private function lens(string $community): Response
    {
        return $this->render('anokii/lens.html.twig', [
            'community' => $community,
            'communityName' => self::COMMUNITIES[$community]['name'] ?? ucfirst($community),
            'communities' => self::COMMUNITIES,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $name, array $context): Response
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Page unavailable: Twig is not initialised.', 500);
        }

        return new Response(
            $twig->render($name, $context),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
