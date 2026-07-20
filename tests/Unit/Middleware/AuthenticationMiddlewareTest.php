<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Contract\FlashInterface;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Middleware\AuthenticationMiddleware;
use App\Security\Contract\AuthCheckerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuthenticationMiddlewareTest extends TestCase
{
    /** @var MockObject&AuthCheckerInterface */
    private $authChecker;

    /** @var MockObject&FlashInterface */
    private $flash;

    /** @var MockObject&ResponderInterface */
    private $responder;

    /** @var MockObject&Request */
    private $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authChecker = $this->createMock(AuthCheckerInterface::class);
        $this->flash       = $this->createMock(FlashInterface::class);
        $this->responder   = $this->createMock(ResponderInterface::class);
        $this->request     = $this->createMock(Request::class);
    }

    private function make(): AuthenticationMiddleware
    {
        return new AuthenticationMiddleware(
            $this->authChecker,
            $this->flash,
            $this->responder
        );
    }

    public function testPublicRouteAllowsRequest(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->never())
            ->method('isAuthenticated');

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $ok = $mw->handle($this->request, '/public-page', 'GET');

        $this->assertTrue($ok);
    }

    public function testProtectedRouteAllowsWhenAuthenticated(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $ok = $mw->handle($this->request, '/change-password', 'GET');

        $this->assertTrue($ok);
    }

    public function testProtectedRouteBlocksWhenNotAuthenticatedAndRedirectsToLogin(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Vous devez être connecté pour accéder à cette page.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $ok = $mw->handle($this->request, '/change-password', 'GET');

        $this->assertFalse($ok);
    }

    public function testAccountRouteIsAlsoProtected(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Vous devez être connecté pour accéder à cette page.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $ok = $mw->handle($this->request, '/account', 'GET');

        $this->assertFalse($ok);
    }
}
