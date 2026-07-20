<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\ConfirmAccountController;
use App\Core\Contract\FlashInterface;
use App\Handler\Auth\ConfirmAccountHandler;
use App\Http\Contract\ResponderInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfirmAccountControllerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;

    private ConfirmAccountController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];

        $this->securityService = $this->createMock(SecurityServiceInterface::class);
        $this->flash           = $this->createMock(FlashInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);

        $handler = new ConfirmAccountHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
        );

        $this->controller = new ConfirmAccountController($handler);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testConfirmAccountDelegatesToHandlerWhenTokenIsPresent(): void
    {
        $_GET['token'] = 'valid-confirm-token';

        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('valid-confirm-token')
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->controller->confirmAccount();
    }

    public function testConfirmAccountHandlesMissingTokenThroughHandler(): void
    {
        $this->securityService
            ->expects($this->never())
            ->method('confirmAccount');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->controller->confirmAccount();
    }
}
