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
        private mixed $previousHttps;
        private mixed $previousForwardedProto;
        private bool $httpsWasDefined;
        private bool $forwardedProtoWasDefined;

        protected function setUp(): void
        {
            parent::setUp();

            HeaderSpy::reset();

            $this->httpsWasDefined          = array_key_exists('HTTPS', $_SERVER);
            $this->forwardedProtoWasDefined = array_key_exists(
                'HTTP_X_FORWARDED_PROTO',
                $_SERVER
            );

            $this->previousHttps          = $_SERVER['HTTPS']                           ?? null;
            $this->previousForwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO']          ?? null;

            unset(
                $_SERVER['HTTPS'],
                $_SERVER['HTTP_X_FORWARDED_PROTO']
            );
        }

        protected function tearDown(): void
        {
            if ($this->httpsWasDefined) {
                $_SERVER['HTTPS'] = $this->previousHttps;
            } else {
                unset($_SERVER['HTTPS']);
            }

            if ($this->forwardedProtoWasDefined) {
                $_SERVER['HTTP_X_FORWARDED_PROTO'] = $this->previousForwardedProto;
            } else {
                unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
            }

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
                $this->headersContain(
                    $headers,
                    'Referrer-Policy: strict-origin-when-cross-origin'
                ),
                'Le header Referrer-Policy doit être présent.'
            );
        }

        public function testHandleDoesNotAddHstsHeaderOverHttp(): void
        {
            $_SERVER['HTTPS'] = 'off';

            $middleware = new SecurityHeadersMiddleware();

            $middleware->handle(
                $this->makeRequest(),
                '/any-uri',
                'GET'
            );

            $headers = HeaderSpy::all();

            self::assertFalse(
                $this->headersContain(
                    $headers,
                    'Strict-Transport-Security:'
                ),
                'Le header HSTS ne doit pas être envoyé en HTTP.'
            );
        }

        public function testHandleAddsHstsHeaderOverHttps(): void
        {
            $_SERVER['HTTPS'] = 'on';

            $middleware = new SecurityHeadersMiddleware();

            $middleware->handle(
                $this->makeRequest(),
                '/any-uri',
                'GET'
            );

            $headers = HeaderSpy::all();

            self::assertTrue(
                $this->headersContain(
                    $headers,
                    'Strict-Transport-Security: max-age=31536000'
                ),
                'Le header HSTS doit être envoyé en HTTPS.'
            );
        }

        public function testHandleAddsHstsHeaderWhenForwardedProtoIsHttps(): void
        {
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

            $middleware = new SecurityHeadersMiddleware();

            $middleware->handle(
                $this->makeRequest(),
                '/any-uri',
                'GET'
            );

            $headers = HeaderSpy::all();

            self::assertTrue(
                $this->headersContain(
                    $headers,
                    'Strict-Transport-Security: max-age=31536000'
                ),
                'Le header HSTS doit être envoyé lorsque le proxy indique HTTPS.'
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
