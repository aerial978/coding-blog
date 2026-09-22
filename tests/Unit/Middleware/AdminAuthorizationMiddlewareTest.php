<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Controller\ErrorController;
use App\Http\Request;
use App\Middleware\AdminAuthorizationMiddleware;
use App\Security\Contract\AuthCheckerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminAuthorizationMiddlewareTest extends TestCase
{
    /** @var MockObject&AuthCheckerInterface */
    private $authChecker;

    /** @var MockObject&ErrorController */
    private $errorController;

    /** @var MockObject&Request */
    private $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authChecker     = $this->createMock(AuthCheckerInterface::class);
        $this->errorController = $this->createMock(ErrorController::class);
        $this->request         = $this->createMock(Request::class);
    }

    private function make(): AdminAuthorizationMiddleware
    {
        return new AdminAuthorizationMiddleware(
            $this->authChecker,
            $this->errorController
        );
    }

    public function testNonAdminRouteAllowsRequest(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->never())
            ->method('isAuthenticated');

        $this->authChecker
            ->expects($this->never())
            ->method('getRoles');

        $this->errorController
            ->expects($this->never())
            ->method('forbidden');

        $ok = $mw->handle($this->request, '/account', 'GET');

        $this->assertTrue($ok);
    }

    public function testAdminRouteAllowsAuthenticatedAdmin(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->authChecker
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->errorController
            ->expects($this->never())
            ->method('forbidden');

        $ok = $mw->handle($this->request, '/admin', 'GET');

        $this->assertTrue($ok);
    }

    public function testAdminSubRouteAllowsAuthenticatedAdmin(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->authChecker
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn(['ADMIN']);

        $this->errorController
            ->expects($this->never())
            ->method('forbidden');

        $ok = $mw->handle($this->request, '/admin/users', 'GET');

        $this->assertTrue($ok);
    }

    public function testAdminRouteBlocksAuthenticatedMember(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->authChecker
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn(['MEMBER']);

        $this->errorController
            ->expects($this->once())
            ->method('forbidden');

        $ok = $mw->handle($this->request, '/admin', 'GET');

        $this->assertFalse($ok);
    }

    public function testAdminSubRouteBlocksAuthenticatedMember(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->authChecker
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn(['MEMBER']);

        $this->errorController
            ->expects($this->once())
            ->method('forbidden');

        $ok = $mw->handle($this->request, '/admin/users', 'GET');

        $this->assertFalse($ok);
    }

    public function testAdminRouteBlocksUnauthenticatedUser(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        $this->authChecker
            ->expects($this->never())
            ->method('getRoles');

        $this->errorController
            ->expects($this->once())
            ->method('forbidden');

        $ok = $mw->handle($this->request, '/admin', 'GET');

        $this->assertFalse($ok);
    }

    public function testSimilarAdminPrefixIsNotConsideredAdminRoute(): void
    {
        $mw = $this->make();

        $this->authChecker
            ->expects($this->never())
            ->method('isAuthenticated');

        $this->authChecker
            ->expects($this->never())
            ->method('getRoles');

        $this->errorController
            ->expects($this->never())
            ->method('forbidden');

        $ok = $mw->handle($this->request, '/administrator', 'GET');

        $this->assertTrue($ok);
    }
}
