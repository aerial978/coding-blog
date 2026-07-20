<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Contract\SessionInterface;
use App\Http\Request;
use App\Security\SessionAuthChecker;
use PHPUnit\Framework\TestCase;

final class SessionAuthCheckerTest extends TestCase
{
    public function testIsAuthenticatedReturnsFalseWhenNoUserInSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn(null);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertFalse($checker->isAuthenticated($request));
    }

    public function testIsAuthenticatedReturnsFalseWhenUserIsNotArray(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn('not-an-array');

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertFalse($checker->isAuthenticated($request));
    }

    public function testIsAuthenticatedReturnsTrueWhenUserIdExists(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn(['id' => 123, 'roles' => ['USER']]);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertTrue($checker->isAuthenticated($request));
    }

    public function testGetRolesReturnsEmptyArrayWhenUserIsNotArray(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn(null);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertSame([], $checker->getRoles($request));
    }

    public function testGetRolesReturnsEmptyArrayWhenRolesIsNotArray(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn(['id' => 123, 'roles' => 'ADMIN']);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertSame([], $checker->getRoles($request));
    }

    public function testGetRolesFiltersOnlyStrings(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn([
            'id'    => 123,
            'roles' => ['USER', 42, null, 'ADMIN', new \stdClass()],
        ]);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertSame(['USER', 'ADMIN'], $checker->getRoles($request));
    }

    public function testGetUserIdReturnsNullWhenNoUser(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn(null);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertNull($checker->getUserId($request));
    }

    public function testGetUserIdReturnsNullWhenIdIsMissing(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn(['roles' => ['USER']]);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertNull($checker->getUserId($request));
    }

    public function testGetUserIdReturnsNullWhenIdIsNotInt(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn(['id' => '123', 'roles' => ['USER']]);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertNull($checker->getUserId($request));
    }

    public function testGetUserIdReturnsUserIdWhenValid(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user')->willReturn(['id' => 123, 'roles' => ['USER']]);

        $checker = new SessionAuthChecker($session);
        $request = $this->createMock(Request::class);

        self::assertSame(123, $checker->getUserId($request));
    }
}
