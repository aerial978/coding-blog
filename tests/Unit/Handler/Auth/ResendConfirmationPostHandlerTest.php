<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Handler\Auth\ResendConfirmationPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResendConfirmationPostHandlerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;

    private ResendConfirmationPostHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityService      = $this->createMock(SecurityServiceInterface::class);
        $this->flash                = $this->createMock(FlashInterface::class);
        $this->responder            = $this->createMock(ResponderInterface::class);
        $this->honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);

        $this->handler = new ResendConfirmationPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function validForm(): array
    {
        return [
            'email' => 'john@example.com',
        ];
    }

    private function allowAllGuards(): void
    {
        $this->honeypotGuard
            ->method('assertClean')
            ->willReturn(true);

        $this->submissionDelayGuard
            ->method('assertPassed')
            ->willReturn(true);

        $this->rateLimitGuard
            ->method('assertAllowed')
            ->willReturn(true);
    }

    public function testHandleStopsWhenHoneypotGuardRejects(): void
    {
        $form = $this->validForm();

        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(false);

        $this->submissionDelayGuard
            ->expects($this->never())
            ->method('assertPassed');

        $this->rateLimitGuard
            ->expects($this->never())
            ->method('assertAllowed');

        $this->securityService
            ->expects($this->never())
            ->method('resendConfirmation');

        $this->handler->handle($form);
    }

    public function testHandleStopsWhenSubmissionDelayGuardRejects(): void
    {
        $form = $this->validForm();

        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(true);

        $this->submissionDelayGuard
            ->expects($this->once())
            ->method('assertPassed')
            ->willReturn(false);

        $this->rateLimitGuard
            ->expects($this->never())
            ->method('assertAllowed');

        $this->securityService
            ->expects($this->never())
            ->method('resendConfirmation');

        $this->handler->handle($form);
    }

    public function testHandleStopsWhenRateLimitGuardRejects(): void
    {
        $form = $this->validForm();

        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(true);

        $this->submissionDelayGuard
            ->expects($this->once())
            ->method('assertPassed')
            ->willReturn(true);

        $this->rateLimitGuard
            ->expects($this->once())
            ->method('assertAllowed')
            ->willReturn(false);

        $this->securityService
            ->expects($this->never())
            ->method('resendConfirmation');

        $this->handler->handle($form);
    }

    public function testHandleRedirectsToFormWhenResendConfirmationThrowsException(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@example.com')
            ->willThrowException(new \RuntimeException('mail failure'));

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                $this->isType('string')
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->handler->handle($form);
    }

    public function testHandleRedirectsToHomeWhenAccountIsAlreadyConfirmed(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'error' => ErrorCode::AUTH_ALREADY_CONFIRMED,
        ];

        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@example.com')
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'info',
                $this->isType('string')
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog');

        $this->handler->handle($form);
    }

    public function testHandleRedirectsToFormWhenBusinessErrorOccurs(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [
            'error' => 'ERR_CUSTOM',
        ];

        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@example.com')
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                $this->isType('string')
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->handler->handle($form);
    }

    public function testHandleAddsSuccessFlashAndRedirectsToFormWhenResendSucceeds(): void
    {
        $form = $this->validForm();
        $this->allowAllGuards();

        $result = [];

        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@example.com')
            ->willReturn($result);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'success',
                $this->isType('string')
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->handler->handle($form);
    }

    public function testHandleTrimsEmailBeforeCallingService(): void
    {
        $form = [
            'email' => '  john@example.com  ',
        ];
        $this->allowAllGuards();

        $this->securityService
            ->expects($this->once())
            ->method('resendConfirmation')
            ->with('john@example.com')
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'success',
                $this->isType('string')
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $this->handler->handle($form);
    }
}
