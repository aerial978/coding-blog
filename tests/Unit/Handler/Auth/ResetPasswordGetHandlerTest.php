<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\View;
use App\Handler\Auth\ResetPasswordGetHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResetPasswordGetHandlerTest extends TestCase
{
    private View&MockObject $view;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotValidatorInterface&MockObject $honeypot;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelay;
    private ResetPasswordServiceInterface&MockObject $resetPasswordService;

    private ResetPasswordGetHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view                 = $this->createMock(View::class);
        $this->flash                = $this->createMock(FlashInterface::class);
        $this->responder            = $this->createMock(ResponderInterface::class);
        $this->csrf                 = $this->createMock(CsrfTokenInterface::class);
        $this->honeypot             = $this->createMock(HoneypotValidatorInterface::class);
        $this->submissionDelay      = $this->createMock(SubmissionDelayValidatorInterface::class);
        $this->resetPasswordService = $this->createMock(ResetPasswordServiceInterface::class);

        $this->handler = new ResetPasswordGetHandler(
            $this->view,
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypot,
            $this->submissionDelay,
            $this->resetPasswordService,
        );
    }

    public function testHandleRedirectsWhenTokenIsInvalid(): void
    {
        $token = 'invalid-token';

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart')
            ->with('reset_password');

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('security_flags', [])
            ->willReturn([]);

        $this->resetPasswordService
            ->expects($this->once())
            ->method('validateResetToken')
            ->with($token)
            ->willReturn([
                'ok'    => false,
                'error' => 'ERR_INVALID_TOKEN',
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $this->responder
            ->expects($this->never())
            ->method('render');

        $this->handler->handle($token);
    }

    public function testHandleRendersFormWhenTokenIsValidWithoutTurnstile(): void
    {
        $token = 'valid-token';

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart')
            ->with('reset_password');

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('security_flags', [])
            ->willReturn([]);

        $this->resetPasswordService
            ->expects($this->once())
            ->method('validateResetToken')
            ->with($token)
            ->willReturn([
                'ok' => true,
            ]);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->willReturn('csrf-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('hp_field');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/reset-password.html.twig',
                $this->callback(function (array $data) use ($token) {
                    return $data['token']              === $token
                        && $data['csrf_token']         === 'csrf-token'
                        && $data['honeypot_name']      === 'hp_field'
                        && $data['turnstile_required'] === false
                        && $data['turnstile_enabled']  === false;
                })
            );

        $this->handler->handle($token);
    }

    public function testHandleRendersFormWithTurnstileWhenFlagIsPresent(): void
    {
        $token = 'valid-token';

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart');

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->willReturn([
                'turnstile_reset' => true,
            ]);

        $this->resetPasswordService
            ->method('validateResetToken')
            ->willReturn(['ok' => true]);

        $this->csrf
            ->method('generateToken')
            ->willReturn('csrf-token');

        $this->honeypot
            ->method('fieldName')
            ->willReturn('hp_field');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                $this->callback(function (array $data) {
                    return $data['turnstile_required'] === true
                        && $data['turnstile_enabled']  === true;
                })
            );

        $this->handler->handle($token);
    }

    public function testHandleTrimsTokenBeforeValidation(): void
    {
        $rawToken = '  valid-token  ';
        $trimmed  = 'valid-token';

        $this->submissionDelay
            ->method('markFormStart');

        $this->flash
            ->method('take')
            ->willReturn([]);

        $this->resetPasswordService
            ->expects($this->once())
            ->method('validateResetToken')
            ->with($trimmed)
            ->willReturn(['ok' => false, 'error' => 'ERR']);

        $this->flash
            ->method('add');

        $this->responder
            ->method('redirect');

        $this->handler->handle($rawToken);
    }
}
