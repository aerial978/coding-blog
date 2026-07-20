<?php

declare(strict_types=1);

namespace App\Security {
    $GLOBALS['__turnstile_stub'] = [
        'next_response' => null,
        'last_url'      => null,
        'last_context'  => null,
        'last_opts'     => null,
    ];

    if (!function_exists(__NAMESPACE__ . '\\stream_context_create')) {
        function stream_context_create(array $options = [])
        {
            $GLOBALS['__turnstile_stub']['last_opts'] = $options;
            return (object) ['stub' => true];
        }
    }

    if (!function_exists(__NAMESPACE__ . '\\file_get_contents')) {
        function file_get_contents(string $filename, bool $use_include_path = false, $context = null)
        {
            $GLOBALS['__turnstile_stub']['last_url']     = $filename;
            $GLOBALS['__turnstile_stub']['last_context'] = $context;

            return $GLOBALS['__turnstile_stub']['next_response'] ?? false;
        }
    }
}

namespace Tests\Unit\Security {

    use App\Security\TurnstileValidator;
    use PHPUnit\Framework\TestCase;

    final class TurnstileValidatorTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            $GLOBALS['__turnstile_stub']['next_response'] = null;
            $GLOBALS['__turnstile_stub']['last_url']      = null;
            $GLOBALS['__turnstile_stub']['last_context']  = null;
            $GLOBALS['__turnstile_stub']['last_opts']     = null;
        }

        public function testIsConfiguredReturnsFalseWhenSecretEmpty(): void
        {
            $v = new TurnstileValidator('');
            self::assertFalse($v->isConfigured());
        }

        public function testIsConfiguredReturnsTrueWhenSecretNonEmpty(): void
        {
            $v = new TurnstileValidator('   s3cr3t   ');
            self::assertTrue($v->isConfigured());
        }

        public function testValidateReturnsTrueWhenNotConfiguredEvenIfTokenMissing(): void
        {
            $v = new TurnstileValidator('');

            self::assertTrue($v->validate(null));
            self::assertTrue($v->validate(''));

            self::assertNull($v->getLastResponse());
            self::assertNull($GLOBALS['__turnstile_stub']['last_url']);
        }

        public function testValidateReturnsFalseWhenConfiguredAndTokenNullOrEmpty(): void
        {
            $v = new TurnstileValidator('secret');

            self::assertFalse($v->validate(null));
            self::assertSame([
                'success'     => false,
                'error-codes' => ['missing-input-response'],
                'diagnostic'  => 'empty_token',
            ], $v->getLastResponse());

            self::assertFalse($v->validate(''));
            self::assertSame([
                'success'     => false,
                'error-codes' => ['missing-input-response'],
                'diagnostic'  => 'empty_token',
            ], $v->getLastResponse());

            self::assertNull($GLOBALS['__turnstile_stub']['last_url']);
        }

        public function testValidateReturnsFalseWhenHttpCallFails(): void
        {
            $v = new TurnstileValidator('secret');

            $GLOBALS['__turnstile_stub']['next_response'] = false;

            $ok = $v->validate('token-ok', '1.2.3.4');
            self::assertFalse($ok);

            self::assertSame(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                $GLOBALS['__turnstile_stub']['last_url']
            );

            $last = $v->getLastResponse();
            self::assertIsArray($last);
            self::assertFalse((bool) ($last['success'] ?? true));
            self::assertSame(['turnstile_request_failed'], $last['error-codes'] ?? null);
            self::assertArrayHasKey('diagnostic', $last);
        }

        public function testValidateStoresLastResponseAndReturnsTrueOnSuccess(): void
        {
            $v = new TurnstileValidator('secret');

            $GLOBALS['__turnstile_stub']['next_response'] = json_encode([
                'success'      => true,
                'challenge_ts' => '2020-01-01T00:00:00Z',
                'hostname'     => 'example.com',
            ]);

            $ok = $v->validate('token-ok', '1.2.3.4');
            self::assertTrue($ok);

            $last = $v->getLastResponse();
            self::assertIsArray($last);
            self::assertTrue((bool) ($last['success'] ?? false));

            $opts = $GLOBALS['__turnstile_stub']['last_opts'];
            self::assertIsArray($opts);
            self::assertSame('POST', $opts['http']['method'] ?? null);

            $content = (string) ($opts['http']['content'] ?? '');
            self::assertStringContainsString('secret=secret', $content);
            self::assertStringContainsString('response=token-ok', $content);
            self::assertStringContainsString('remoteip=1.2.3.4', $content);
        }

        public function testValidateReturnsFalseWhenSuccessIsFalseAndStillStoresLastResponse(): void
        {
            $v = new TurnstileValidator('secret');

            $GLOBALS['__turnstile_stub']['next_response'] = json_encode([
                'success'     => false,
                'error-codes' => ['invalid-input-response'],
            ]);

            $ok = $v->validate('bad-token', null);
            self::assertFalse($ok);

            $last = $v->getLastResponse();
            self::assertIsArray($last);
            self::assertFalse((bool) ($last['success'] ?? true));
            self::assertSame(['invalid-input-response'], $last['error-codes'] ?? null);
        }

        public function testValidateStoresStructuredErrorWhenJsonIsInvalid(): void
        {
            $v = new TurnstileValidator('secret');

            $GLOBALS['__turnstile_stub']['next_response'] = '{invalid json';

            $ok = $v->validate('token-ok', null);
            self::assertFalse($ok);

            self::assertSame([
                'success'     => false,
                'error-codes' => ['turnstile_bad_response'],
                'diagnostic'  => 'invalid_json',
            ], $v->getLastResponse());
        }

        public function testValidateResetsLastResponseAtBeginningOfEachCall(): void
        {
            $v = new TurnstileValidator('secret');

            $GLOBALS['__turnstile_stub']['next_response'] = json_encode(['success' => true]);
            self::assertTrue($v->validate('token-ok', null));
            self::assertNotNull($v->getLastResponse());

            $GLOBALS['__turnstile_stub']['next_response'] = false;
            self::assertFalse($v->validate('token-ok', null));

            $last = $v->getLastResponse();
            self::assertIsArray($last);
            self::assertFalse((bool) ($last['success'] ?? true));
            self::assertSame(['turnstile_request_failed'], $last['error-codes'] ?? null);
        }
    }
}
