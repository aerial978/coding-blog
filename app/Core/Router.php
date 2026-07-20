<?php

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
     * Normalizes the request URI by removing the basePath.
     *
     * @param string $uri URI to sanitize.
     * @return string Normalized URI.
     */
    private function normalizeUri(string $uri): string
    {
        if (str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
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

        if (!isset($this->routes[$method][$uri])) {
            $this->handleError(404);
            return;
        }

        [$controllerClass, $action] = $this->routes[$method][$uri];

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
            $this->handleError(500);
            return;
        }

        $controller = $this->controllerFactory->create($controllerClass);

        $controller->$action();
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
            echo "<h1>$code - An error has occurred</h1>";
        }
    }

    public function addMiddleware(MiddlewareInterface $mw): void
    {
        $this->middlewares[] = $mw;
    }
}
