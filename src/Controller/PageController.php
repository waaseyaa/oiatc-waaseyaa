<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\SSR\SsrResponse;

final class PageController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function home(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return $this->render('home.html.twig', '/');
    }

    public function about(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return $this->render('about.html.twig', '/about');
    }

    public function waaseyaa(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return $this->render('waaseyaa.html.twig', '/waaseyaa');
    }

    public function minoo(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return $this->render('minoo.html.twig', '/minoo');
    }

    public function grants(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return $this->render('grants.html.twig', '/grants');
    }

    public function charter(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return $this->render('founding-charter.html.twig', '/founding-charter');
    }

    public function contact(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return $this->render('contact.html.twig', '/contact');
    }

    public function notFound(array $params, array $query, $account, HttpRequest $request): SsrResponse
    {
        return new SsrResponse($this->twig->render('404.html.twig', ['path' => $request->getPathInfo()]), 404);
    }

    private function render(string $template, string $path): SsrResponse
    {
        return new SsrResponse($this->twig->render($template, ['path' => $path]));
    }
}
