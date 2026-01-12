<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Request;
use App\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nettoyage des headers entre tests
        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    protected function tearDown(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        parent::tearDown();
    }

    public function testHandleAddsSecurityHeadersAndAllowsRequest(): void
    {
        $middleware = new SecurityHeadersMiddleware();

        $request = $this->makeRequest();

        $result = $middleware->handle($request, '/any-uri', 'GET');

        self::assertTrue($result);

        $headers = headers_list();

        self::assertTrue(
            $this->headersContain($headers, 'X-Frame-Options: SAMEORIGIN'),
            'Le header X-Frame-Options doit être présent.'
        );

        self::assertTrue(
            $this->headersContain($headers, 'X-Content-Type-Options: nosniff'),
            'Le header X-Content-Type-Options doit être présent.'
        );

        self::assertTrue(
            $this->headersContain($headers, 'Referrer-Policy: strict-origin-when-cross-origin'),
            'Le header Referrer-Policy doit être présent.'
        );
    }

    private function makeRequest(): Request
    {
        // Le middleware ne lit pas Request, une instance minimale suffit
        return new Request();
    }

    private function headersContain(array $headers, string $needle): bool
    {
        foreach ($headers as $header) {
            if (stripos($header, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
