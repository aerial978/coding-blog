<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Handler\Auth\LogoutHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LogoutHandlerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private RememberMeCookieManagerInterface&MockObject $rememberMeCookieManager;

    private LogoutHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityService         = $this->createMock(SecurityServiceInterface::class);
        $this->flash                   = $this->createMock(FlashInterface::class);
        $this->responder               = $this->createMock(ResponderInterface::class);
        $this->rememberMeCookieManager = $this->createMock(RememberMeCookieManagerInterface::class);



        $this->handler = new LogoutHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->rememberMeCookieManager,
        );
    }

    public function testHandleLogsOutAddsSuccessFlashAndRedirectsToLogin(): void
    {
        $this->securityService
            ->expects($this->once())
            ->method('logout');

        $this->rememberMeCookieManager
            ->expects($this->once())
            ->method('expireCookie');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'success',
                MessageManager::get(ErrorCode::AUTH_LOGOUT_SUCCESS)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle();
    }
}
