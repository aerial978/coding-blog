<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Handler\Auth\ConfirmAccountHandler;
use App\Http\Contract\ResponderInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfirmAccountHandlerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;

    private ConfirmAccountHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];

        $this->securityService = $this->createMock(SecurityServiceInterface::class);
        $this->flash           = $this->createMock(FlashInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);

        $this->handler = new ConfirmAccountHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testHandleRedirectsToResendConfirmationWhenTokenIsMissing(): void
    {
        $_GET = [];

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

        $this->handler->handle();
    }

    public function testHandleRedirectsToHomeWhenServiceThrowsException(): void
    {
        $_GET = [
            'token' => 'valid-token',
        ];

        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('valid-token')
            ->willThrowException(new \RuntimeException('db failure'));

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle();
    }

    public function testHandleRedirectsToHomeWhenConfirmationSucceeds(): void
    {
        $_GET = [
            'token' => 'valid-token',
        ];

        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('valid-token')
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle();
    }

    public function testHandleRedirectsToResendConfirmationWhenTokenIsExpired(): void
    {
        $_GET = [
            'token' => 'expired-token',
        ];

        $result = [
            'error'  => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN,
            'reason' => 'expired',
        ];

        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('expired-token')
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->handler->handle();
    }

    public function testHandleRedirectsToResendConfirmationWhenTokenIsNotFound(): void
    {
        $_GET = [
            'token' => 'unknown-token',
        ];

        $result = [
            'error'  => ErrorCode::AUTH_INVALID_CONFIRM_TOKEN,
            'reason' => 'not_found',
        ];

        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('unknown-token')
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->handler->handle();
    }

    public function testHandleRedirectsToHomeWhenTokenWasAlreadyUsed(): void
    {
        $_GET = [
            'token' => 'used-token',
        ];

        $result = [
            'error' => ErrorCode::AUTH_CONFIRM_TOKEN_USED,
        ];

        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('used-token')
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('info', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle();
    }

    public function testHandleRedirectsToHomeWhenAccountIsAlreadyConfirmed(): void
    {
        $_GET = [
            'token' => 'already-confirmed-token',
        ];

        $result = [
            'error' => ErrorCode::AUTH_ALREADY_CONFIRMED,
        ];

        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('already-confirmed-token')
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('info', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle();
    }

    public function testHandleRedirectsToHomeWhenUnknownErrorOccurs(): void
    {
        $_GET = [
            'token' => 'weird-token',
        ];

        $result = [
            'error' => 'SOME_UNKNOWN_ERROR',
        ];

        $this->securityService
            ->expects($this->once())
            ->method('confirmAccount')
            ->with('weird-token')
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle();
    }
}
