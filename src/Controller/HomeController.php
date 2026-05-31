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

    public function robinsonHuronTreatyExplainer(): Response
    {
        return $this->renderTemplate('explainers/robinson-huron-treaty.html.twig');
    }

    public function robinsonHuronTreatyDistributionModels(): Response
    {
        return $this->renderTemplate('explainers/robinson-huron-treaty-distribution-models.html.twig');
    }

    public function masseySolarProjectExplainer(): Response
    {
        return $this->renderTemplate('explainers/massey-solar-project.html.twig');
    }

    public function masseySolarProjectWhatYouveHeard(): Response
    {
        return $this->renderTemplate('explainers/massey-solar-project-what-youve-heard.html.twig');
    }

    public function masseySolarProjectVoices(): Response
    {
        return $this->renderTemplate('explainers/massey-solar-project-voices.html.twig');
    }

    public function practiceAiInCoursework(): Response
    {
        return $this->renderTemplate('practice/ai-in-coursework.html.twig');
    }

    public function sagamokPortalDisclosure(): Response
    {
        return $this->renderTemplate('disclosure/sagamok-portal.html.twig');
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
            $twig->render($name, []),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
