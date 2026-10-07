<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\ErrorController;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;

/**
 * Main application router
 */
class Router
{
    public const METHOD_GET  = 'GET';
    public const METHOD_POST = 'POST';

    /**
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    private array $routes;
    /** @internal Used internally by Router but not read yet */
    private string $basePath;
    private ErrorController $errorController;
    private Request $request;
    private ControllerFactoryInterface $controllerFactory;
    /** @var list<\App\Http\Middleware\MiddlewareInterface> */
    private array $middlewares = [];

    /**
     * @param array<string, array<string, array{0: string, 1: string}>> $routes
     */
    public function __construct(
        array $routes,
        string $basePath,
        ErrorController $errorController,
        Request $request,
        ControllerFactoryInterface $controllerFactory
    ) {
        $this->routes            = $routes;
        $this->basePath          = rtrim($basePath, '/');
        $this->errorController   = $errorController;
        $this->request           = $request;
        $this->controllerFactory = $controllerFactory;
    }

    /**
     * Handles the current HTTP request by calling the corresponding route or returning an error.
     */
    public function handleRequest(): void
    {
        $requestUri = $this->request->getUri();

        if (!is_string($requestUri) || $requestUri === '') {
            $this->handleError(500);
            return;
        }

        $path       = (string) parse_url($requestUri, PHP_URL_PATH);
        $cleanedUri = $this->normalizeUri($path);

        $method = $this->request->getMethod();
        foreach ($this->middlewares as $mw) {
            if ($mw->handle($this->request, $cleanedUri, $method) === false) {
                return; // middleware a géré la réponse
            }
        }

        $this->dispatch($cleanedUri);
    }

    /**
     * Normalizes the request URI by removing the configured base path.
     *
     * The base path is removed only when it matches the complete URI
     * or represents a complete leading path segment.
     *
     * @param string $uri URI path to normalize.
     * @return string Normalized URI.
     */
    private function normalizeUri(string $uri): string
    {
        if ($this->basePath !== '') {
            if ($uri === $this->basePath) {
                return '/';
            }

            if (str_starts_with($uri, $this->basePath . '/')) {
                $uri = substr($uri, strlen($this->basePath));
            }
        }

        return $uri === '' ? '/' : $uri;
    }

    /**
     * Executes the route matching the URI, otherwise displays an error.
     *
     * @param string $uri URI already sanitized.
     */
    private function dispatch(string $uri): void
    {
        $method = $this->request->getMethod();

        $route     = $this->routes[$method][$uri] ?? null;
        $parameter = null;

        if ($route === null) {
            $match = $this->matchDynamicRoute($method, $uri);

            if ($match === null) {
                $this->handleError(404);
                return;
            }

            $route     = $match['route'];
            $parameter = $match['parameter'];
        }

        [$controllerClass, $action] = $route;

        if (!class_exists($controllerClass)) {
            $this->handleError(500);
            return;
        }

        $controller = $this->controllerFactory->create($controllerClass);

        if (!is_callable([$controller, $action])) {
            $this->handleError(500);
            return;
        }

        if ($parameter !== null) {
            $controller->$action($parameter);
            return;
        }

        $controller->$action();
    }

    /**
     * Finds a dynamic route matching the requested URI.
     *
     * Supports one dynamic parameter per route, using the "{parameter}" syntax.
     *
     * @return array{
     *     route: array{0: string, 1: string},
     *     parameter: string
     * }|null
     */
    private function matchDynamicRoute(string $method, string $uri): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $routePath => $route) {
            if (!str_contains($routePath, '{')) {
                continue;
            }

            $pattern = preg_quote($routePath, '#');
            $pattern = preg_replace(
                '#\\\\\{[^/]+\\\\\}#',
                '([^/]+)',
                $pattern,
                1
            );

            if (!is_string($pattern)) {
                continue;
            }

            if (preg_match('#^' . $pattern . '$#', $uri, $matches) !== 1) {
                continue;
            }

            return [
                'route'     => $route,
                'parameter' => $matches[1],
            ];
        }

        return null;
    }

    /**
     * Displays an HTTP error (404 or 500) via the error controller.
     *
     * @param int $code HTTP code.
     */
    private function handleError(int $code): void
    {
        http_response_code($code);

        try {
            match ($code) {
                404     => $this->errorController->notFound(),
                500     => $this->errorController->serverError(),
                default => $this->errorController->serverError(),
            };
        } catch (\Throwable $e) {
            Logger::getLogger('error')->error('ErrorController failed', [
                'http_code' => $code,
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);

            echo "<h1>$code - An error has occurred</h1>";
        }
    }

    public function addMiddleware(MiddlewareInterface $mw): void
    {
        $this->middlewares[] = $mw;
    }
}
