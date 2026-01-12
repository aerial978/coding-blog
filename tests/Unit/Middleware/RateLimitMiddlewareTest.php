<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\Contract\RateLimiterInterface;
use App\Http\Request;
use App\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nettoyage des headers entre tests (important pour Retry-After / Location)
        if (function_exists('header_remove')) {
            header_remove();
        }

        // Base serveur "stable"
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        unset($_SERVER['HTTP_REFERER']);
    }

    protected function tearDown(): void
    {
        // Évite que la session ouverte dans un test pollue les autres tests
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        if (function_exists('header_remove')) {
            header_remove();
        }

        parent::tearDown();
    }

    public function testHandleAllowsWhenNoRuleMatches(): void
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $flash   = $this->createMock(FlashInterface::class);

        // Pas de règle pour GET:/register => ne doit pas créer de limiter
        $factory->expects(self::never())->method('create');
        $flash->expects(self::never())->method('add');

        $mw = new RateLimitMiddleware($factory, $flash);

        $request = $this->makeRequest();

        $allowed = $mw->handle($request, '/register', 'GET');

        self::assertTrue($allowed);
    }

    public function testHandleRecordsAttemptAndAllowsWhenWithinLimit(): void
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $flash   = $this->createMock(FlashInterface::class);

        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter->expects(self::once())
            ->method('isAllowed')
            ->willReturn(true);

        $limiter->expects(self::once())
            ->method('recordAttempt');

        $flash->expects(self::never())->method('add');

        // bucket attendu : POST:/register:client-ip-1.2.3.4
        $factory->expects(self::once())
            ->method('create')
            ->with('POST:/register:client-ip-1.2.3.4', 5, 300)
            ->willReturn($limiter);

        $mw = new RateLimitMiddleware($factory, $flash);

        $request = $this->makeRequest();

        $allowed = $mw->handle($request, '/register', 'POST');

        self::assertTrue($allowed);

        $headers = $this->getSentHeaders();
        self::assertFalse($this->headersContain($headers, 'Retry-After:'), 'Retry-After ne doit pas être envoyé si autorisé.');
        self::assertFalse($this->headersContain($headers, 'Location:'), 'Location ne doit pas être envoyé si autorisé.');
    }

    public function testHandleBlocksAddsFlashAndSendsRetryAfterAndRedirectToReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = '/security/register';

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $flash   = $this->createMock(FlashInterface::class);

        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter->expects(self::once())
            ->method('isAllowed')
            ->willReturn(false);

        $limiter->expects(self::once())
            ->method('getRetryAfter')
            ->willReturn(125);

        // Bloqué => ne doit PAS enregistrer d’attempt
        $limiter->expects(self::never())
            ->method('recordAttempt');

        // Flash message : on ne dépend pas du texte exact (MessageManager),
        // on vérifie juste qu'un message d'erreur est posé.
        $flash->expects(self::once())
            ->method('add')
            ->with(
                'error',
                self::isType('string')
            );

        $factory->expects(self::once())
            ->method('create')
            ->with('POST:/register:client-ip-1.2.3.4', 5, 300)
            ->willReturn($limiter);

        $mw = new RateLimitMiddleware($factory, $flash);

        $request = $this->makeRequest();

        $allowed = $mw->handle($request, '/register', 'POST');

        self::assertFalse($allowed);

        $headers = $this->getSentHeaders();

        self::assertTrue(
            $this->headersContain($headers, 'Retry-After: 125') || $this->headersContain($headers, 'Retry-After:125'),
            'Le header Retry-After doit être présent.'
        );

        self::assertTrue(
            $this->headersContain($headers, 'Location: /security/register'),
            'Le header Location doit rediriger vers HTTP_REFERER si présent.'
        );
    }

    public function testHandleBlocksRedirectsToUriWhenNoReferer(): void
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $flash   = $this->createMock(FlashInterface::class);

        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter->expects(self::once())
            ->method('isAllowed')
            ->willReturn(false);

        $limiter->expects(self::once())
            ->method('getRetryAfter')
            ->willReturn(10);

        $flash->expects(self::once())
            ->method('add')
            ->with('error', self::isType('string'));

        $factory->expects(self::once())
            ->method('create')
            ->with('POST:/register:client-ip-1.2.3.4', 5, 300)
            ->willReturn($limiter);

        $mw = new RateLimitMiddleware($factory, $flash);

        $request = $this->makeRequest();

        $allowed = $mw->handle($request, '/register', 'POST');

        self::assertFalse($allowed);

        $headers = $this->getSentHeaders();

        self::assertTrue(
            $this->headersContain($headers, 'Location: /register'),
            'Sans referer, la redirection doit retomber sur l’URI courant.'
        );
    }

    public function testHandleUsesSessionIdWhenSessionIsActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $sid = session_id();
        self::assertIsString($sid);
        self::assertNotSame('', $sid);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $flash   = $this->createMock(FlashInterface::class);

        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter->expects(self::once())
            ->method('isAllowed')
            ->willReturn(true);

        $limiter->expects(self::once())
            ->method('recordAttempt');

        // bucket attendu : POST:/register:client-<sid>
        $factory->expects(self::once())
            ->method('create')
            ->with('POST:/register:client-' . $sid, 5, 300)
            ->willReturn($limiter);

        $mw = new RateLimitMiddleware($factory, $flash);

        $allowed = $mw->handle($this->makeRequest(), '/register', 'POST');

        self::assertTrue($allowed);
    }

    public function testHandleFallsBackToDefaultIpWhenRemoteAddrMissing(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $flash   = $this->createMock(FlashInterface::class);

        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter->expects(self::once())
            ->method('isAllowed')
            ->willReturn(true);

        $limiter->expects(self::once())
            ->method('recordAttempt');

        $factory->expects(self::once())
            ->method('create')
            ->with('POST:/register:client-ip-0.0.0.0', 5, 300)
            ->willReturn($limiter);

        $mw = new RateLimitMiddleware($factory, $flash);

        $allowed = $mw->handle($this->makeRequest(), '/register', 'POST');

        self::assertTrue($allowed);
    }

    /**
     * IMPORTANT :
     * - RateLimitMiddleware ne lit pas $request, donc l’instance peut être minimale.
     * - Si Request impose un constructeur spécifique, adaptez cette méthode en conséquence.
     */
    private function makeRequest(): Request
    {
        // Option 1 (si Request a un constructeur sans argument)
        return new Request();

        // Option 2 (exemple si besoin, à adapter) :
        // return new Request($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);
    }

    /**
     * Récupère la liste des headers envoyés durant le test.
     */
    private function getSentHeaders(): array
    {
        /** @var array<int, string> $headers */
        $headers = headers_list();
        return $headers;
    }

    private function headersContain(array $headers, string $needle): bool
    {
        foreach ($headers as $h) {
            if (stripos($h, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
