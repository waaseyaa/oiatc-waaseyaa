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

    /**
     * Suggested starter questions shown as clickable pills under the chat input,
     * per vantage community. Clicking one fills the input and submits it down the
     * same path as Ask. Kept per community, not hardcoded to Sagamok.
     *
     * @var array<string, list<string>>
     */
    private const SUGGESTED_PROMPTS = [
        'sagamok' => [
            'How do I apply for housing?',
            'I want to start a business',
            'Where can I get mental health support?',
            'I need to see a doctor, where do I go?',
            'How do I apply for Ontario Works?',
            'How do I bring something to Council?',
        ],
        'massey' => [
            'What is the Massey Solar Project?',
            'Will the solar project help with climate change?',
            'What about the farmland, water, and wildlife?',
            'What happens to the panels at the end of their life?',
            'Where is the project in the approval process?',
            'How does the project relate to Sagamok?',
        ],
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
            'suggestedPrompts' => self::SUGGESTED_PROMPTS[$community] ?? [],
            // Mirrors the /api/chat wiring: when web research is enabled the chat
            // disclaimer tells members answers may draw on public web sources.
            'webResearch' => (getenv('ANOKII_WEB_RESEARCH') ?: '') === '1',
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
