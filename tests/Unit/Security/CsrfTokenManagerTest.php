<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\SessionManager;
use App\Security\CsrfTokenManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CsrfTokenManagerTest extends TestCase
{
    private SessionManager $session;
    private CsrfTokenManager $csrf;
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->session = new SessionManager();
        // reset CSRF bag
        $this->session->set('_csrf', []);
        $this->csrf = new CsrfTokenManager($this->session);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
    }

    #[Test]
    public function generateToken_stores_single_use_and_is_hex_64(): void
    {
        $t1 = $this->csrf->generateToken('formA');
        $t2 = $this->csrf->generateToken('formB');
        self::assertSame(64, \strlen($t1));
        self::assertSame(64, \strlen($t2));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/i', $t1);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/i', $t2);
        self::assertSame(64, strlen($t1));
        // bin2hex(32) => 64 chars
        self::assertSame(64, strlen($t2));
        self::assertTrue(ctype_xdigit($t1));
        self::assertTrue(ctype_xdigit($t2));
        self::assertNotSame($t1, $t2);
        $bag = (array)$this->session->get('_csrf', []);
        self::assertArrayHasKey('formA', $bag);
        self::assertArrayHasKey('formB', $bag);
    }

    #[Test]
    public function validateToken_returns_false_if_missing_or_empty(): void
    {
        self::assertFalse($this->csrf->validateToken('formX', null));
        self::assertFalse($this->csrf->validateToken('formX', ''));
    }

    #[Test]
    public function validateToken_success_then_consumes_entry(): void
    {
        $token = $this->csrf->generateToken('reg');
        // first time: valid
        self::assertTrue($this->csrf->validateToken('reg', $token));
        // bag consumed for this form
        $bag = (array)$this->session->get('_csrf', []);
        self::assertArrayNotHasKey('reg', $bag);
        // second time with same token: now invalid
        self::assertFalse($this->csrf->validateToken('reg', $token));
    }

    #[Test]
    public function wrong_token_consumes_and_makes_next_validation_fail(): void
    {
        $token = $this->csrf->generateToken('login');
        // wrong token → false
        self::assertFalse($this->csrf->validateToken('login', 'not_the_token'));
        // consumed, so even the correct one fails afterwards
        self::assertFalse($this->csrf->validateToken('login', $token));
    }

    #[Test]
    public function forForm_and_isValidForForm_aliases_work(): void
    {
        $tok = $this->csrf->forForm('contact');
        self::assertTrue($this->csrf->isValidForForm('contact', $tok));
        // consumed → second check false
        self::assertFalse($this->csrf->isValidForForm('contact', $tok));
    }
}
