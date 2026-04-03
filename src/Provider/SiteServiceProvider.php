<?php

declare(strict_types=1);

namespace App\Provider;

use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function routes(WaaseyaaRouter $router, ?\Waaseyaa\Entity\EntityTypeManager $entityTypeManager = null): void
    {
        $routes = [
            'page.home' => ['/', 'home'],
            'page.about' => ['/about', 'about'],
            'page.waaseyaa' => ['/waaseyaa', 'waaseyaa'],
            'page.minoo' => ['/minoo', 'minoo'],
            'page.grants' => ['/grants', 'grants'],
            'page.charter' => ['/founding-charter', 'charter'],
            'page.contact' => ['/contact', 'contact'],
        ];

        foreach ($routes as $name => [$path, $method]) {
            $router->addRoute(
                $name,
                RouteBuilder::create($path)
                    ->controller(sprintf('App\\Controller\\PageController::%s', $method))
                    ->render()
                    ->methods('GET')
                    ->build(),
            );
        }

        // 404 catch-all (must be last)
        $router->addRoute(
            'page.not_found',
            RouteBuilder::create('/{path}')
                ->controller('App\\Controller\\PageController::notFound')
                ->render()
                ->methods('GET')
                ->requirement('path', '.+')
                ->build(),
        );
    }

    public function commands(
        \Waaseyaa\Entity\EntityTypeManager $entityTypeManager,
        \Waaseyaa\Database\DatabaseInterface $database,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
    ): array {
        return [];
    }
}
