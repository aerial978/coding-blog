<?php

declare(strict_types=1);

namespace App\Middleware {
    final class HeaderSpy
    {
        /** @var list<string> */
        private static array $headers = [];

        public static function reset(): void
        {
            self::$headers = [];
        }

        public static function add(string $header): void
        {
            self::$headers[] = $header;
        }

        /**
         * @return list<string>
         */
        public static function all(): array
        {
            return self::$headers;
        }
    }

    function header(string $header, bool $replace = true, int $responseCode = 0): void
    {
        HeaderSpy::add($header);
    }
}

namespace Tests\Unit\Middleware {

    use App\Http\Request;
    use App\Middleware\HeaderSpy;
    use App\Middleware\SecurityHeadersMiddleware;
    use PHPUnit\Framework\TestCase;

    final class SecurityHeadersMiddlewareTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            HeaderSpy::reset();
        }

        protected function tearDown(): void
        {
            HeaderSpy::reset();
            parent::tearDown();
        }

        public function testHandleAddsSecurityHeadersAndAllowsRequest(): void
        {
            $middleware = new SecurityHeadersMiddleware();

            $request = $this->makeRequest();

            $result = $middleware->handle($request, '/any-uri', 'GET');

            self::assertTrue($result);

            $headers = HeaderSpy::all();

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
            return new Request();
        }

        /**
         * @param list<string> $headers
         */
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
}
