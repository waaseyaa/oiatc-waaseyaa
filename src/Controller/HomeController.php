<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

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

    public function redirectToHome(): RedirectResponse
    {
        return new RedirectResponse('/', 301);
    }

    private function renderTemplate(string $name): Response
    {
        $path = dirname(__DIR__, 2) . '/templates/' . $name;
        $html = (string) file_get_contents($path);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
