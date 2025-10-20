<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Represents a simplified HTTP request abstraction.
 *
 * This class provides lightweight access to core HTTP request data
 * such as URI, method, query parameters, and POST data. It acts as
 * a minimal alternative to Symfony’s `Request` object for smaller
 * or custom frameworks.
 */
class Request
{
    /**
     * Returns the raw request URI.
     *
     * Example: `/coding-blog/register?x=1`
     *
     * @return string|null
     *     The request URI string, or null if unavailable.
     */
    public function getUri(): ?string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        return is_string($uri) ? $uri : null;
    }

    /**
     * Returns the HTTP method of the current request.
     *
     * Ensures the method is always uppercase.
     * Example: "GET", "POST", "PUT", "DELETE".
     *
     * @return string
     *     The request method (defaults to "GET" if unavailable).
     */
    public function getMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return is_string($method) ? strtoupper($method) : 'GET';
    }

    /** @return array<string, mixed> */
    private function sanitizeInputArray(mixed $in): array
    {
        if (!\is_array($in)) {
            return [];
        }

        /** @var array<string, mixed> $out */
        $out = [];
        foreach ($in as $k => $v) {
            if (\is_string($k)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Returns the raw POST data.
     *
     * Provides access to submitted form fields or payload data.
     * Mirrors the functionality of `$request->request()` in frameworks.
     *
     * @return array<string, mixed>
     *     An associative array containing POST parameters.
     */
    public function request(): array
    {
        // éviter le warning si $_POST est unset
        return $this->sanitizeInputArray($GLOBALS['_POST'] ?? null);
    }

    /**
     * Returns the raw GET query parameters.
     *
     * Provides direct access to query string values.
     *
     * @return array<string, mixed>
     *     An associative array containing GET parameters.
     */
    public function query(): array
    {
        // éviter le warning si $_GET est unset
        return $this->sanitizeInputArray($GLOBALS['_GET'] ?? null);
    }
}
