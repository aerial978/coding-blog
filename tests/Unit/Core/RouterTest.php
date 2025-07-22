<?php

namespace Tests\Unit\Core;

use App\Core\Router;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\DummyController;

/**
 * Unit test of the App\Core\Router class.
 */
class RouterTest extends TestCase
{
    /**
     * Checks that the display works for a valid route.
     */
    public function testValidRouteOutputsExpectedContent(): void
    {
        $_SERVER['REQUEST_URI']    = '/hello';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $routes = [
            Router::METHOD_GET => [
                '/hello' => [DummyController::class, 'hello'],
            ]
        ];

        ob_start();
        $router = new Router($routes);
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertIsString($output);

        $this->assertStringContainsString('Hello from dummy controller', $output);
    }

    /**
     * Checks that a nonexistent route triggers a 404 error.
     */
    public function testUnknownRouteTriggers404(): void
    {
        $_SERVER['REQUEST_URI']    = '/not-found';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $routes = [
            Router::METHOD_GET => [
                '/' => [DummyController::class, 'hello'],
            ]
        ];

        ob_start();
        $router = new Router($routes);
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertIsString($output);

        $this->assertStringContainsString('404', $output);
    }

    /**
     * Checks for invalid controllers that trigger a 500 error.
     */
    public function testInvalidControllerTriggers500(): void
    {
        $_SERVER['REQUEST_URI']    = '/fail';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $fakeClass = 'App\\Controller\\Inexistant';

        $routes = [
            Router::METHOD_GET => [
                '/fail' => [$fakeClass, 'fail']
            ]
        ];

        ob_start();
        $router = new Router($routes);
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertIsString($output);

        $this->assertStringContainsString('500', $output);
    }

    /**
     * Checks that an invalid HTTP method returns a 404.
     */
    public function testInvalidMethodTriggers404(): void
    {
        $_SERVER['REQUEST_URI']    = '/hello';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $routes = [
            Router::METHOD_GET => [
                '/hello' => [DummyController::class, 'hello'],
            ]
        ];

        ob_start();
        $router = new Router($routes);
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertIsString($output);

        $this->assertStringContainsString('404', $output);
    }

    /**
     * Checks that the absence of a URI triggers a 500 error.
     */
    public function testHandleRequestWithNullRequestUriShouldTriggerError500(): void
    {
        $_SERVER['REQUEST_URI'] = null;

        $this->expectOutputRegex('/500/');

        $router = new Router(
            [
                Router::METHOD_GET => ['/test' => [DummyController::class, 'index']]
            ]
        );

        $router->handleRequest();
    }

    /**
     * Checks that normalizeUri correctly removes the basePath.
     */
    public function testNormalizeUriRemovesBasePath(): void
    {
        $_SERVER['REQUEST_METHOD'] = Router::METHOD_GET;
        $_SERVER['REQUEST_URI']    = '/coding-blog/test';

        $router = new Router(
            [
                Router::METHOD_GET => ['/test' => [DummyController::class, 'index']]
            ]
        );

        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertIsString($output);

        $this->assertStringContainsString('Méthode index exécutée', $output);
    }

    /**
     * Checks that errors from the ErrorController trigger the HTML fallback.
     */
    public function testHandleErrorWithFaultyErrorControllerFallback(): void
    {
        $_SERVER['REQUEST_METHOD'] = Router::METHOD_GET;
        $_SERVER['REQUEST_URI']    = '/invalid';

        $mock = $this->createMock(\App\Controller\ErrorController::class);
        $mock->method('notFound')->will($this->throwException(new Exception()));
        $mock->method('serverError')->will($this->throwException(new Exception()));

        $router = new Router(
            [
                \App\Core\Router::METHOD_GET => [
                    '/valide' => [DummyController::class, 'index']
                ]
            ],
            $mock // injection du contrôleur fautif
        );

        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertIsString($output);

        $this->assertStringContainsString('404 - An error has occurred', $output);
    }
}
