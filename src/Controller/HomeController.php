<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\SSR\SsrServiceProvider;

final class HomeController
{
    public function index(): Response
    {
        return $this->renderTemplate('home.html.twig');
    }

    public function designSystem(): Response
    {
        return $this->renderTemplate('design-system.html.twig');
    }

    public function counterDisinformationPosition(): Response
    {
        return $this->renderTemplate('positions/counter-disinformation.html.twig');
    }

    public function prescribeitPosition(): Response
    {
        return $this->renderTemplate('positions/prescribeit.html.twig');
    }

    public function sovereignAiPosition(): Response
    {
        return $this->renderTemplate('positions/sovereign-ai.html.twig');
    }

    public function practiceAiInCoursework(): Response
    {
        return $this->renderTemplate('practice/ai-in-coursework.html.twig');
    }

    public function anishinaabemowin(): Response
    {
        return $this->renderTemplate('anishinaabemowin/home.html.twig');
    }

    public function anishinaabemowinDoll(): Response
    {
        return $this->renderTemplate('anishinaabemowin/doll.html.twig');
    }

    public function anishinaabemowinDollBuild(): Response
    {
        return $this->renderTemplate('anishinaabemowin/doll-build.html.twig');
    }

    public function anishinaabemowinDollProcess(): Response
    {
        return $this->renderTemplate('anishinaabemowin/doll-process.html.twig');
    }

    public function about(): Response
    {
        return $this->renderTemplate('about.html.twig');
    }

    public function support(): Response
    {
        return $this->renderTemplate('support.html.twig');
    }

    public function programs(): Response
    {
        return $this->renderTemplate('programs/index.html.twig');
    }

    public function programAnokii(): Response
    {
        return $this->renderTemplate('programs/anokii.html.twig');
    }

    public function programCommunityKnowledge(): Response
    {
        return $this->renderTemplate('programs/community-knowledge.html.twig');
    }

    public function whereYourDataLives(): Response
    {
        return $this->renderTemplate('explainers/where-your-data-lives.html.twig');
    }

    public function redirectToHome(): RedirectResponse
    {
        return new RedirectResponse('/', 301);
    }

    private function renderTemplate(string $name): Response
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Page unavailable: Twig is not initialised.', 500);
        }

        return new Response(
            $twig->render($name, $this->socialContext($name)),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    /**
     * Compute per-page OG/social context for the base template.
     *
     * - `og_image_url`: per-page card from `public/images/og/<slug>.png` if the
     *   auto-OG generator (`scripts/generate-og.js`) has produced one, else the
     *   site default `og-default.png` as the failsafe.
     * - `og_url`: canonical URL derived from the template name, matching the
     *   route map. Pages can still override either via their `{% block head_meta %}`.
     *
     * Slug rule mirrors `scripts/generate-og.js`: replace `/` with `-`, strip
     * `.html.twig`. Route overrides cover the one template whose URL isn't the
     * derived path (home).
     *
     * @return array<string, string>
     */
    private function socialContext(string $templateName): array
    {
        $routeOverrides = [
            'home.html.twig' => '/',
        ];

        $slug = str_replace('/', '-', preg_replace('/\.html\.twig$/', '', $templateName));
        $autoCardRelPath = '/images/og/' . $slug . '.png';
        $autoCardAbsPath = dirname(__DIR__, 2) . '/public' . $autoCardRelPath;

        $ogImageUrl = is_file($autoCardAbsPath)
            ? 'https://oiatc.ca' . $autoCardRelPath
            : 'https://oiatc.ca/images/og-default.png';

        $routePath = $routeOverrides[$templateName]
            ?? '/' . preg_replace('/\.html\.twig$/', '', $templateName);
        $ogUrl = rtrim('https://oiatc.ca' . $routePath, '/') ?: 'https://oiatc.ca';

        return [
            'og_image_url' => $ogImageUrl,
            'og_url' => $ogUrl,
        ];
    }
}
