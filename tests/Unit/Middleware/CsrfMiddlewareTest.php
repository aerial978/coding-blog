<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Http\Request;
use App\Middleware\CsrfMiddleware;
use App\Security\Contract\CsrfTokenInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de CsrfMiddleware
 *
 * On ne teste pas ici les effets externes (header PHP réel),
 * mais les retours booléens et les appels Flash.
 */
final class CsrfMiddlewareTest extends TestCase
{
    /** @var MockObject&CsrfTokenInterface */
    private $csrf;
    /** @var MockObject&FlashInterface */
    private $flash;
    /** @var MockObject&Request */
    private $request;
    protected function setUp(): void
    {
        $this->csrf    = $this->createMock(CsrfTokenInterface::class);
        $this->flash   = $this->createMock(FlashInterface::class);
        $this->request = $this->createMock(Request::class);
    }

    private function makeMiddleware(): CsrfMiddleware
    {
        return new CsrfMiddleware($this->csrf, $this->flash);
    }

    // --------------------------------------------------------------------
    // 1) Méthode GET → toujours autorisée, peu importe la route
    // --------------------------------------------------------------------
    public function test_get_method_is_always_allowed(): void
    {
        $mw = $this->makeMiddleware();
        $this->assertTrue($mw->handle($this->request, '/register', 'GET'));
    }

    // --------------------------------------------------------------------
    // 2) Route POST non protégée → autorisée sans vérification
    // --------------------------------------------------------------------
    public function test_post_non_protected_route_is_allowed(): void
    {
        $mw = $this->makeMiddleware();
        // méthode POST mais URI non dans PROTECTED_POST_ROUTES
        $this->csrf->expects($this->never())->method('validateToken');
        $result = $mw->handle($this->request, '/unrelated-route', 'POST');
        $this->assertTrue($result);
    }

    // --------------------------------------------------------------------
    // 3) Route POST protégée + token valide → autorisée
    // --------------------------------------------------------------------
    public function test_post_protected_route_with_valid_token_is_allowed(): void
    {
        $this->request->method('request')->willReturn(['csrf_token' => 'abc123']);
        $this->csrf->expects($this->once())
            ->method('validateToken')
            ->with(FormId::REGISTER, 'abc123')
            ->willReturn(true);
        $mw     = $this->makeMiddleware();
        $result = $mw->handle($this->request, '/register', 'POST');
        $this->assertTrue($result);
    }

    // --------------------------------------------------------------------
    // 4) Route POST protégée + token invalide → bloquée
    // --------------------------------------------------------------------
    #[RunInSeparateProcess]  // isole le test (recommandé quand header() est appelé)
    public function test_post_protected_route_with_invalid_token_is_blocked_and_flash_error(): void
    {
        $this->request->method('request')->willReturn(['csrf_token' => 'bad-token']);
        $this->csrf->method('validateToken')->willReturn(false);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));
        // pas de referer -> redirection vers '/'
        $_SERVER['HTTP_REFERER'] = '';
        $mw                      = $this->makeMiddleware();
        $result                  = $mw->handle($this->request, '/register', 'POST');
        $this->assertFalse($result, 'Le middleware doit bloquer la requête');
    }

    // --------------------------------------------------------------------
    // 5) Token manquant dans la requête → bloquée aussi
    // --------------------------------------------------------------------
    #[RunInSeparateProcess]
    public function test_post_protected_route_without_token_is_blocked(): void
    {
        $this->request->method('request')->willReturn([]);
        // pas de csrf_token
        $this->csrf->method('validateToken')->willReturn(false);
        $this->flash->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));
        $_SERVER['HTTP_REFERER'] = '/previous/page';
        $mw                      = $this->makeMiddleware();
        $result                  = $mw->handle($this->request, '/resend-confirmation', 'POST');
        $this->assertFalse($result);
    }
}
