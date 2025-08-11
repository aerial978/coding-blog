<?php

namespace Tests\Functional;

use App\Core\Container;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Functional test to ensure that the home page is correctly rendered using the Twig template engine.
 *
 * This test simulates a GET request to the root route ("/"),
 * invokes the router, and checks that the output contains expected HTML.
 */
class TwigIntegrationTest extends TestCase
{
    /**
     * Simulates a request to the home page and checks the HTML output rendered by Twig.
     *
     * Assertions:
     * - Output must be a string.
     * - Output must contain the HTML doctype declaration.
     * - Output must contain expected translated content from the Twig template,
     *   which includes both the page title and body text.
     *
     * Important: this test assumes the HomeController is wired
     * to render `home/index.html.twig` with `title` and `message` variables.
     */
    public function testHomePageRenderedWithTwig(): void
    {
        // Simulate an HTTP GET request to the homepage
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Load application routes
        /** @var array<string, array<string, array{string, string}>> $routes */
        $routes    = require __DIR__ . '/../../app/config/routes.php';
        $container = new Container();

        $router = new Router(
            $routes,
            $container,
            $container->getErrorController(),
            $container->getRequest()
        );

        // Capture the output of the response
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();

        // Validate the output
        $this->assertIsString($output);
        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Home', $output);
        $this->assertStringContainsString('This is the home page.', $output);
    }
}
