<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Contract\FlashInterface;
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
    /** @var MockObject&Request */
    private $request;
    protected function setUp(): void
    {
        $this->authChecker = $this->createMock(AuthCheckerInterface::class);
        $this->flash       = $this->createMock(FlashInterface::class);
        $this->request     = $this->createMock(Request::class);
        // Par précaution, nettoie les en-têtes éventuels entre les tests
        if (function_exists('header_remove')) {
            @header_remove();
        }
    }

    protected function tearDown(): void
    {
        if (function_exists('header_remove')) {
            @header_remove();
        }
    }

    private function make(): AuthenticationMiddleware
    {
        return new AuthenticationMiddleware($this->authChecker, $this->flash);
    }

    public function test_public_route_allows_request(): void
    {
        // Route NON listée dans PROTECTED_ROUTES
        $mw = $this->make();
        $this->authChecker->expects($this->never())->method('isAuthenticated');
        $this->flash->expects($this->never())->method('add');
        $ok = $mw->handle($this->request, '/public-page', 'GET');
        $this->assertTrue($ok);
        $this->assertSame([], headers_list(), 'Aucun header ne doit être envoyé');
    }

    public function test_protected_route_allows_when_authenticated(): void
    {
        // Route protégée
        $mw = $this->make();
        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->with($this->request)
            ->willReturn(true);
        $this->flash->expects($this->never())->method('add');
        $ok = $mw->handle($this->request, '/change-password', 'GET');
        $this->assertTrue($ok);
        $this->assertSame([], headers_list(), 'Aucun header ne doit être envoyé si authentifié');
    }

    public function test_protected_route_blocks_when_not_authenticated_and_redirects_to_login(): void
    {
        $mw = $this->make();
        $this->authChecker
            ->expects($this->once())
            ->method('isAuthenticated')
            ->with($this->request)
            ->willReturn(false);
        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));
        // Appel
        $ok = $mw->handle($this->request, '/change-password', 'GET');
        $this->assertFalse($ok);
        // En CLI, header() remplit headers_list()
        $headers = headers_list();
        $this->assertNotEmpty($headers);
        $this->assertTrue((bool)array_filter($headers, fn (string $h) => stripos($h, 'Location: /login') === 0), 'Doit rediriger vers /login');
    }
}
