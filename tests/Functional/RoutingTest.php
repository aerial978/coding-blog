<?php

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;

/**
 * Functional test of the routing system with the home page.
 */
class RoutingTest extends TestCase
{
    /**
     * Checks that the home page is reachable and displays the expected text.
     */
    public function testHomePageIsReachable(): void
    {
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        /** @var array<string, array<string, array{0: class-string, 1: string}>> */
        $routes = require __DIR__ . '/../../app/config/routes.php';
        $router = new \App\Core\Router($routes);

        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertIsString($output);

        $this->assertStringContainsString('Bienvenue', $output);
    }
}
