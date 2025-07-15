<?php

namespace App\Http;

/**
 * Represents an HTTP request.
 */
class Request
{
    /**
     * Returns the request URI or null if not defined.
     */
    public function getUri(): ?string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        return is_string($uri) ? $uri : null;
    }

    /**
     * Returns the HTTP method (GET, POST, etc.) or 'GET' by default.
     */
    public function getMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return is_string($method) ? $method : 'GET';
    }
}
