<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Handler\Auth\ResetPasswordPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Support\ErrorListNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResetPasswordPostHandlerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;
    private TurnstileGuardInterface&MockObject $turnstileGuard;
    private ErrorListNormalizer $errorListNormalizer;

    private ResetPasswordPostHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityService      = $this->createMock(SecurityServiceInterface::class);
        $this->flash                = $this->createMock(FlashInterface::class);
        $this->responder            = $this->createMock(ResponderInterface::class);
        $this->honeypotGuard        = $this->createMock(HoneypotGuardInterface::class);
        $this->submissionDelayGuard = $this->createMock(SubmissionDelayGuardInterface::class);
        $this->rateLimitGuard       = $this->createMock(RateLimitGuardInterface::class);
        $this->turnstileGuard       = $this->createMock(TurnstileGuardInterface::class);
        $this->errorListNormalizer  = new ErrorListNormalizer();

        $this->handler = new ResetPasswordPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            $this->turnstileGuard,
            $this->errorListNormalizer,
        );
    }

    private function validForm(): array
    {
        return [
            'token'                 => 'valid-token',
            'password'              => 'StrongPassword123!',
            'password_confirm'      => 'StrongPassword123!',
            'cf-turnstile-response' => 'valid-token',
        ];
    }

    private function allowGuards(): void
    {
        $this->honeypotGuard->method('assertClean')->willReturn(true);
        $this->submissionDelayGuard->method('assertPassed')->willReturn(true);
        $this->rateLimitGuard->method('assertAllowed')->willReturn(true);
    }

    private function redirectWithToken(): string
    {
        return '/coding-blog/reset-password?token=valid-token';
    }

    public function testStopsWhenHoneypotFails(): void
    {
        $this->honeypotGuard
            ->expects($this->once())
            ->method('assertClean')
            ->willReturn(false);

        $this->securityService->expects($this->never())->method('resetPassword');

        $this->handler->handle($this->validForm());
    }

    public function testStopsWhenSubmissionDelayFails(): void
    {
        $this->honeypotGuard->method('assertClean')->willReturn(true);

        $this->submissionDelayGuard
            ->expects($this->once())
            ->method('assertPassed')
            ->willReturn(false);

        $this->securityService->expects($this->never())->method('resetPassword');

        $this->handler->handle($this->validForm());
    }

    public function testStopsWhenRateLimitFails(): void
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

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->securityService
            ->expects($this->never())
            ->method('resetPassword');

        $this->handler->handle($form);
    }

    public function testSkipsTurnstileWhenNotRequired(): void
    {
        $this->allowGuards();

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('security_flags', []);

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->securityService
            ->expects($this->once())
            ->method('resetPassword');

        $this->handler->handle($this->validForm());
    }

    public function testStopsWhenTurnstileFails(): void
    {
        $this->allowGuards();

        $this->flash->method('take')->willReturn(['turnstile_reset' => true]);
        $this->flash->expects($this->once())->method('put');

        $this->turnstileGuard
            ->expects($this->once())
            ->method('assertValid')
            ->willReturn(false);

        $this->securityService->expects($this->never())->method('resetPassword');

        $this->handler->handle($this->validForm());
    }

    public function testHandlesValidationErrors(): void
    {
        $this->allowGuards();

        $this->flash->method('take')->willReturn([]);

        $this->flash->expects($this->once())->method('put');

        $this->securityService
            ->method('resetPassword')
            ->willReturn([
                'errors' => ['ERR_1', 'ERR_2'],
            ]);

        $this->flash
            ->expects($this->exactly(2))
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with($this->redirectWithToken());

        $this->handler->handle($this->validForm());
    }

    public function testHandlesSingleError(): void
    {
        $this->allowGuards();

        $this->flash->method('take')->willReturn([]);
        $this->flash->expects($this->once())->method('put');

        $this->securityService
            ->method('resetPassword')
            ->willReturn([
                'error' => 'ERR_SINGLE',
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with($this->redirectWithToken());

        $this->handler->handle($this->validForm());
    }

    public function testHandlesSuccess(): void
    {
        $this->allowGuards();

        $this->flash->method('take')->willReturn([]);
        $this->flash->expects($this->once())->method('put');

        $this->securityService
            ->method('resetPassword')
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $this->handler->handle($this->validForm());
    }
}
