<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Controller\ErrorController;
use App\Core\ControllerFactoryInterface;
use App\Core\Router;
use App\Http\Request;
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
            ],
        ];

        $errorController = $this->createMock(ErrorController::class);
        $request         = new Request();

        $controllerMock = $this->getMockBuilder(DummyController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['hello'])
            ->getMock();

        $controllerMock->expects($this->once())
            ->method('hello')
            ->willReturnCallback(static function (): void {
                echo 'Hello from dummy controller';
            });

        $factory = $this->createMock(ControllerFactoryInterface::class);
        $factory->method('create')
            ->with(DummyController::class)
            ->willReturn($controllerMock);

        $router = new Router($routes, '', $errorController, $request, $factory);

        ob_start();
        $router->handleRequest();
        $output = (string) ob_get_clean();

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
            ],
        ];

        $errorController = $this->createMock(ErrorController::class);
        $errorController->expects($this->once())
            ->method('notFound')
            ->willReturnCallback(static function (): void {
                echo '404 - Not found';
            });

        $request = new Request();

        $factory = $this->createMock(ControllerFactoryInterface::class);

        $router = new Router($routes, '', $errorController, $request, $factory);

        ob_start();
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

        $fakeClass = 'App\\Controller\\Nonexistent';

        $routes = [
            Router::METHOD_GET => [
                '/fail' => [$fakeClass, 'fail'],
            ],
        ];

        $errorController = $this->createMock(ErrorController::class);
        $errorController->expects($this->once())
            ->method('serverError')
            ->willReturnCallback(static function (): void {
                echo '500 - Server error';
            });

        $request = new Request();

        $factory = $this->createMock(ControllerFactoryInterface::class);

        $router = new Router($routes, '', $errorController, $request, $factory);

        ob_start();
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
            ],
        ];

        $errorController = $this->createMock(ErrorController::class);
        $errorController->expects($this->once())
            ->method('notFound')
            ->willReturnCallback(static function (): void {
                echo '404 - Not found';
            });

        $request = new Request();
        $factory = $this->createMock(ControllerFactoryInterface::class);

        $router = new Router($routes, '', $errorController, $request, $factory);

        ob_start();
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
        $request = $this->createMock(Request::class);
        $request->method('getUri')->willReturn(null);
        $request->method('getMethod')->willReturn('GET');

        $routes = [
            Router::METHOD_GET => [
                '/test' => [DummyController::class, 'index'],
            ],
        ];

        $errorController = $this->createMock(ErrorController::class);
        $errorController->expects($this->once())
            ->method('serverError')
            ->willReturnCallback(static function (): void {
                echo '500 - Server error';
            });

        $factory = $this->createMock(ControllerFactoryInterface::class);

        $router = new Router($routes, '', $errorController, $request, $factory);

        ob_start();
        $router->handleRequest();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('500', $output);
    }

    /**
     * Checks that normalizeUri correctly removes the basePath.
     */
    public function testNormalizeUriRemovesBasePath(): void
    {
        $_SERVER['REQUEST_METHOD'] = Router::METHOD_GET;
        $_SERVER['REQUEST_URI']    = '/coding-blog/test';

        $routes = [
            Router::METHOD_GET => [
                '/test' => [DummyController::class, 'index'],
            ],
        ];

        $errorController = $this->createMock(ErrorController::class);
        $request         = new Request();

        $controllerMock = $this->getMockBuilder(DummyController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['index'])
            ->getMock();

        $controllerMock->expects($this->once())
            ->method('index')
            ->willReturnCallback(static function (): void {
                echo 'Index method executed';
            });

        $factory = $this->createMock(ControllerFactoryInterface::class);
        $factory->method('create')
            ->with(DummyController::class)
            ->willReturn($controllerMock);

        $router = new Router(
            $routes,
            '/coding-blog',
            $errorController,
            $request,
            $factory
        );

        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Index method executed', $output);
    }

    /**
     * Checks that an URI exactly matching the basePath is normalized to "/".
     */
    public function testNormalizeUriReturnsRootWhenUriEqualsBasePath(): void
    {
        $_SERVER['REQUEST_METHOD'] = Router::METHOD_GET;
        $_SERVER['REQUEST_URI']    = '/coding-blog';

        $routes = [
            Router::METHOD_GET => [
                '/' => [DummyController::class, 'index'],
            ],
        ];

        $errorController = $this->createMock(ErrorController::class);
        $request         = new Request();

        $controllerMock = $this->getMockBuilder(DummyController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['index'])
            ->getMock();

        $controllerMock->expects($this->once())
            ->method('index')
            ->willReturnCallback(static function (): void {
                echo 'Root route executed';
            });

        $factory = $this->createMock(ControllerFactoryInterface::class);
        $factory->method('create')
            ->with(DummyController::class)
            ->willReturn($controllerMock);

        $router = new Router(
            $routes,
            '/coding-blog',
            $errorController,
            $request,
            $factory
        );

        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Root route executed', $output);
    }

    /**
     * Checks that a partial basePath match is not removed.
     */
    public function testNormalizeUriDoesNotRemovePartialBasePathMatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = Router::METHOD_GET;
        $_SERVER['REQUEST_URI']    = '/coding-blogger/test';

        $routes = [
            Router::METHOD_GET => [
                '/coding-blogger/test' => [DummyController::class, 'index'],
            ],
        ];

        $errorController = $this->createMock(ErrorController::class);
        $request         = new Request();

        $controllerMock = $this->getMockBuilder(DummyController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['index'])
            ->getMock();

        $controllerMock->expects($this->once())
            ->method('index')
            ->willReturnCallback(static function (): void {
                echo 'Partial basePath preserved';
            });

        $factory = $this->createMock(ControllerFactoryInterface::class);
        $factory->method('create')
            ->with(DummyController::class)
            ->willReturn($controllerMock);

        $router = new Router(
            $routes,
            '/coding-blog',
            $errorController,
            $request,
            $factory
        );

        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Partial basePath preserved', $output);
    }

    /**
     * Checks that errors from the ErrorController trigger the HTML fallback.
     */
    public function testHandleErrorWithFaultyErrorControllerFallback(): void
    {
        $routes = [
            'GET' => [
                '/boom' => [\App\Controller\HomeController::class, 'index'],
            ],
        ];

        $request = $this->createMock(\App\Http\Request::class);
        $request->method('getUri')->willReturn('/inconnu');
        $request->method('getMethod')->willReturn('GET');

        $faultyErrorController = $this->createMock(\App\Controller\ErrorController::class);
        $faultyErrorController->method('notFound')
            ->willThrowException(new \RuntimeException('boom'));

        $factory = $this->createMock(\App\Core\ControllerFactoryInterface::class);

        $router = new \App\Core\Router(
            $routes,
            '',
            $faultyErrorController,
            $request,
            $factory
        );

        ob_start();
        $router->handleRequest();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString(
            '404 - An error has occurred',
            $output
        );
    }
}
