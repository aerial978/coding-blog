<?php

declare(strict_types=1);

namespace App\Security;

final class RememberMeCookieSpy
{
    /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }
}

function setcookie(string $name, string $value = '', array|int $expires_or_options = 0): bool
{
    RememberMeCookieSpy::$calls[] = [
        'name'    => $name,
        'value'   => $value,
        'options' => is_array($expires_or_options) ? $expires_or_options : [],
    ];

    return true;
}

namespace Tests\Unit\Security;

use App\Security\RememberMeCookieManager;
use App\Security\RememberMeCookieSpy;
use PHPUnit\Framework\TestCase;

final class RememberMeCookieManagerTest extends TestCase
{
    private RememberMeCookieManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        RememberMeCookieSpy::reset();

        $_COOKIE = [];

        unset(
            $_SERVER['HTTPS'],
            $_SERVER['SERVER_PORT'],
            $_SERVER['HTTP_X_FORWARDED_PROTO']
        );

        $this->manager = new RememberMeCookieManager();
    }

    public function testCreateCookieSendsRememberMeCookie(): void
    {
        $before = time();

        $this->manager->createCookie(' raw-token ');

        $after = time();

        self::assertCount(1, RememberMeCookieSpy::$calls);

        $call = RememberMeCookieSpy::$calls[0];

        self::assertSame('remember_me', $call['name']);
        self::assertSame('raw-token', $call['value']);

        self::assertGreaterThanOrEqual(
            $before + 2_592_000,
            $call['options']['expires']
        );

        self::assertLessThanOrEqual(
            $after + 2_592_000,
            $call['options']['expires']
        );

        self::assertSame('/coding-blog', $call['options']['path']);
        self::assertFalse($call['options']['secure']);
        self::assertTrue($call['options']['httponly']);
        self::assertSame('Lax', $call['options']['samesite']);
    }

    public function testCreateCookieDoesNothingWhenTokenIsEmpty(): void
    {
        $this->manager->createCookie('   ');

        self::assertSame([], RememberMeCookieSpy::$calls);
    }

    public function testExpireCookieSendsExpiredCookie(): void
    {
        $before = time();

        $this->manager->expireCookie();

        $after = time();

        self::assertCount(1, RememberMeCookieSpy::$calls);

        $call = RememberMeCookieSpy::$calls[0];

        self::assertSame('remember_me', $call['name']);
        self::assertSame('', $call['value']);

        self::assertGreaterThanOrEqual(
            $before - 3600,
            $call['options']['expires']
        );

        self::assertLessThanOrEqual(
            $after - 3600,
            $call['options']['expires']
        );

        self::assertSame('/coding-blog', $call['options']['path']);
        self::assertFalse($call['options']['secure']);
        self::assertTrue($call['options']['httponly']);
        self::assertSame('Lax', $call['options']['samesite']);
    }

    public function testGetCookieValueReturnsNullWhenCookieIsMissing(): void
    {
        self::assertNull($this->manager->getCookieValue());
    }

    public function testGetCookieValueReturnsNullWhenCookieIsNotString(): void
    {
        $_COOKIE['remember_me'] = ['invalid'];

        self::assertNull($this->manager->getCookieValue());
    }

    public function testGetCookieValueReturnsNullWhenCookieIsEmpty(): void
    {
        $_COOKIE['remember_me'] = '   ';

        self::assertNull($this->manager->getCookieValue());
    }

    public function testGetCookieValueReturnsTrimmedCookieValue(): void
    {
        $_COOKIE['remember_me'] = ' raw-token ';

        self::assertSame('raw-token', $this->manager->getCookieValue());
    }

    public function testCreateCookieUsesSecureFlagWhenHttpsIsOn(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $this->manager->createCookie('raw-token');

        self::assertTrue(
            RememberMeCookieSpy::$calls[0]['options']['secure']
        );
    }

    public function testCreateCookieUsesSecureFlagWhenServerPortIs443(): void
    {
        $_SERVER['SERVER_PORT'] = '443';

        $this->manager->createCookie('raw-token');

        self::assertTrue(
            RememberMeCookieSpy::$calls[0]['options']['secure']
        );
    }

    public function testCreateCookieUsesSecureFlagWhenForwardedProtoIsHttps(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        $this->manager->createCookie('raw-token');

        self::assertTrue(
            RememberMeCookieSpy::$calls[0]['options']['secure']
        );
    }
}
