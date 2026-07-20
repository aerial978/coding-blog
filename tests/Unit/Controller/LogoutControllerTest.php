<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\LogoutController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Handler\Auth\LogoutHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\RememberMeCookieManagerInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LogoutControllerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private RememberMeCookieManagerInterface&MockObject $rememberMeCookieManager;


    private LogoutController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityService          = $this->createMock(SecurityServiceInterface::class);
        $this->flash                    = $this->createMock(FlashInterface::class);
        $this->responder                = $this->createMock(ResponderInterface::class);
        $this->rememberMeCookieManager  = $this->createMock(RememberMeCookieManagerInterface::class);

        $handler = new LogoutHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->rememberMeCookieManager,
        );

        $this->controller = new LogoutController($handler);
    }

    public function testLogoutDelegatesToHandlerAndExecutesLogoutFlow(): void
    {
        $this->securityService
            ->expects($this->once())
            ->method('logout');

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

        $this->controller->logout();
    }
}
