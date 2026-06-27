<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\OAuth;

use App\Core\Contract\SessionInterface;
use App\Handler\OAuth\GoogleOAuthStartHandler;
use App\Http\Contract\ResponderInterface;
use App\Service\OAuth\Contract\GoogleOAuthServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GoogleOAuthStartHandlerTest extends TestCase
{
    private GoogleOAuthServiceInterface&MockObject $googleOAuthService;

    private SessionInterface&MockObject $session;

    private ResponderInterface&MockObject $responder;

    private GoogleOAuthStartHandler $handler;

    protected function setUp(): void
    {
        $this->googleOAuthService = $this->createMock(
            GoogleOAuthServiceInterface::class
        );

        $this->session = $this->createMock(
            SessionInterface::class
        );

        $this->responder = $this->createMock(
            ResponderInterface::class
        );

        $this->handler = new GoogleOAuthStartHandler(
            $this->googleOAuthService,
            $this->session,
            $this->responder
        );
    }

    public function testHandleStoresStateAndRedirectsToGoogle(): void
    {
        $authorizationUrl = 'https://accounts.google.com/oauth2/auth';
        $state            = 'oauth-state-123';

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->willReturn($authorizationUrl);

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getState')
            ->willReturn($state);

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('google_oauth_state', $state);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with($authorizationUrl);

        $this->handler->handle();
    }

    public function testHandleRedirectsToLoginWhenAuthorizationUrlIsEmpty(): void
    {
        $this->googleOAuthService
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->willReturn('');

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getState')
            ->willReturn('valid-state');

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle();
    }

    public function testHandleRedirectsToLoginWhenStateIsEmpty(): void
    {
        $this->googleOAuthService
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->willReturn('https://accounts.google.com/oauth2/auth');

        $this->googleOAuthService
            ->expects($this->once())
            ->method('getState')
            ->willReturn('');

        $this->session
            ->expects($this->never())
            ->method('set');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle();
    }
}
