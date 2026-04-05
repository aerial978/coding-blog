<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Handler\Auth\ForgotPasswordPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\Contract\HoneypotGuardInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ForgotPasswordPostHandlerTest extends TestCase
{
    private SecurityServiceInterface&MockObject $securityService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private HoneypotGuardInterface&MockObject $honeypotGuard;
    private SubmissionDelayGuardInterface&MockObject $submissionDelayGuard;
    private RateLimitGuardInterface&MockObject $rateLimitGuard;
    private TurnstileGuardInterface&MockObject $turnstileGuard;

    private ForgotPasswordPostHandler $handlerWithTurnstile;
    private ForgotPasswordPostHandler $handlerWithoutTurnstile;

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

        $this->handlerWithTurnstile = new ForgotPasswordPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            $this->turnstileGuard,
        );

        $this->handlerWithoutTurnstile = new ForgotPasswordPostHandler(
            $this->securityService,
            $this->flash,
            $this->responder,
            $this->honeypotGuard,
            $this->submissionDelayGuard,
            $this->rateLimitGuard,
            null,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function validForm(): array
    {
        return [
            'identifier'            => 'john@example.com',
            'cf-turnstile-response' => 'valid-turnstile-token',
        ];
    }

    private function allowPrimaryGuards(): void
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

    public function testHandleRejectsEmptyIdentifier(): void
    {
        $form = [
            'identifier' => '   ',
        ];

        $this->honeypotGuard
            ->expects($this->never())
            ->method('assertClean');

        $this->submissionDelayGuard
            ->expects($this->never())
            ->method('assertPassed');

        $this->rateLimitGuard
            ->expects($this->never())
            ->method('assertAllowed');

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->securityService
            ->expects($this->never())
            ->method('forgotPassword');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', ['identifier' => '']);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $this->handlerWithTurnstile->handle($form);
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

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->securityService
            ->expects($this->never())
            ->method('forgotPassword');

        $this->handlerWithTurnstile->handle($form);
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

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->securityService
            ->expects($this->never())
            ->method('forgotPassword');

        $this->handlerWithTurnstile->handle($form);
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

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->securityService
            ->expects($this->never())
            ->method('forgotPassword');

        $this->handlerWithTurnstile->handle($form);
    }

    public function testHandleRequestsResetAndRepliesNeutralSuccessWhenTurnstileIsDisabled(): void
    {
        $form = $this->validForm();
        $this->allowPrimaryGuards();

        $this->securityService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with('john@example.com');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', ['identifier' => 'john@example.com']);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $this->handlerWithoutTurnstile->handle($form);
    }

    public function testHandleSkipsTurnstileWhenFlagIsNotRequired(): void
    {
        $form = $this->validForm();
        $this->allowPrimaryGuards();

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('security_flags', [])
            ->willReturn([]);

        $this->flash
            ->expects($this->exactly(2))
            ->method('put')
            ->willReturnCallback(function (string $key, mixed $value): void {
                static $calls = 0;
                $calls++;

                if ($calls === 1) {
                    TestCase::assertSame('security_flags', $key);
                    TestCase::assertSame([], $value);
                    return;
                }

                TestCase::assertSame('old', $key);
                TestCase::assertSame(['identifier' => 'john@example.com'], $value);
            });

        $this->turnstileGuard
            ->expects($this->never())
            ->method('assertValid');

        $this->securityService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with('john@example.com');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $this->handlerWithTurnstile->handle($form);
    }

    public function testHandleStopsWhenTurnstileIsRequiredAndValidationFails(): void
    {
        $form = $this->validForm();
        $this->allowPrimaryGuards();

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('security_flags', [])
            ->willReturn(['turnstile_forgot' => true]);

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('security_flags', ['turnstile_forgot' => true]);

        $this->turnstileGuard
            ->expects($this->once())
            ->method('assertValid')
            ->willReturn(false);

        $this->securityService
            ->expects($this->never())
            ->method('forgotPassword');

        $this->handlerWithTurnstile->handle($form);
    }

    public function testHandleRequestsResetWhenTurnstileIsRequiredAndValidationSucceeds(): void
    {
        $form = $this->validForm();
        $this->allowPrimaryGuards();

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('security_flags', [])
            ->willReturn(['turnstile_forgot' => true]);

        $this->turnstileGuard
            ->expects($this->once())
            ->method('assertValid')
            ->willReturn(true);

        $this->securityService
            ->expects($this->once())
            ->method('forgotPassword')
            ->with('john@example.com');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->flash
            ->expects($this->exactly(2))
            ->method('put')
            ->willReturnCallback(function (string $key, mixed $value): void {
                static $calls = 0;
                $calls++;

                if ($calls === 1) {
                    TestCase::assertSame('security_flags', $key);
                    TestCase::assertSame(['turnstile_forgot' => true], $value);
                    return;
                }

                TestCase::assertSame('old', $key);
                TestCase::assertSame(['identifier' => 'john@example.com'], $value);
            });

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $this->handlerWithTurnstile->handle($form);
    }
}
