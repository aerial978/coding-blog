<?php

declare(strict_types=1);
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Request;
use App\Middleware\RememberMeMiddleware;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Service\Security\Contract\RememberMeServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RememberMeMiddlewareTest extends TestCase
{
    private AuthCheckerInterface&MockObject $authChecker;
    private RememberMeCookieManagerInterface&MockObject $cookieManager;
    private RememberMeServiceInterface&MockObject $rememberMeService;

    private RememberMeMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authChecker       = $this->createMock(AuthCheckerInterface::class);
        $this->cookieManager     = $this->createMock(RememberMeCookieManagerInterface::class);
        $this->rememberMeService = $this->createMock(RememberMeServiceInterface::class);

        $this->middleware = new RememberMeMiddleware(
            $this->authChecker,
            $this->cookieManager,
            $this->rememberMeService
        );
    }

    private function makeRequest(): Request
    {
        return new Request();
    }

    public function testHandleReturnsTrueWhenUserAlreadyAuthenticated(): void
    {
        $request = $this->makeRequest();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->cookieManager
            ->expects($this->never())
            ->method('getCookieValue');

        $this->rememberMeService
            ->expects($this->never())
            ->method('restoreSessionFromToken');

        $result = $this->middleware->handle(
            $request,
            '/coding-blog',
            'GET'
        );

        $this->assertTrue($result);
    }

    public function testHandleReturnsTrueWhenNoRememberMeCookieExists(): void
    {
        $request = $this->makeRequest();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        $this->cookieManager
            ->expects($this->once())
            ->method('getCookieValue')
            ->willReturn(null);

        $this->rememberMeService
            ->expects($this->never())
            ->method('restoreSessionFromToken');

        $result = $this->middleware->handle(
            $request,
            '/coding-blog/login',
            'GET'
        );

        $this->assertTrue($result);
    }

    public function testHandleRestoresSessionWhenRememberMeCookieIsValid(): void
    {
        $request = $this->makeRequest();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        $this->cookieManager
            ->expects($this->once())
            ->method('getCookieValue')
            ->willReturn('raw-token');

        $this->rememberMeService
            ->expects($this->once())
            ->method('restoreSessionFromToken')
            ->with('raw-token')
            ->willReturn(true);

        $this->cookieManager
            ->expects($this->never())
            ->method('expireCookie');

        $result = $this->middleware->handle(
            $request,
            '/coding-blog/account',
            'GET'
        );

        $this->assertTrue($result);
    }

    public function testHandleExpiresCookieWhenRememberMeRestoreFails(): void
    {
        $request = $this->makeRequest();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        $this->cookieManager
            ->expects($this->once())
            ->method('getCookieValue')
            ->willReturn('invalid-token');

        $this->rememberMeService
            ->expects($this->once())
            ->method('restoreSessionFromToken')
            ->with('invalid-token')
            ->willReturn(false);

        $this->cookieManager
            ->expects($this->once())
            ->method('expireCookie');

        $result = $this->middleware->handle(
            $request,
            '/coding-blog/account',
            'GET'
        );

        $this->assertTrue($result);
    }
}
