<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Handler\Auth\ResendConfirmationGetHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResendConfirmationGetHandlerTest extends TestCase
{
    private FlashInterface&MockObject $flash;

    private ResponderInterface&MockObject $responder;

    private SubmissionDelayValidatorInterface&MockObject $submissionDelay;

    private CsrfTokenInterface&MockObject $csrf;

    private HoneypotValidatorInterface&MockObject $honeypot;

    private ResendConfirmationGetHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flash            = $this->createMock(FlashInterface::class);
        $this->responder        = $this->createMock(ResponderInterface::class);
        $this->submissionDelay  = $this->createMock(SubmissionDelayValidatorInterface::class);
        $this->csrf             = $this->createMock(CsrfTokenInterface::class);
        $this->honeypot         = $this->createMock(HoneypotValidatorInterface::class);

        $this->handler = new ResendConfirmationGetHandler(
            $this->flash,
            $this->responder,
            $this->submissionDelay,
            $this->csrf,
            $this->honeypot,
        );
    }

    public function testHandleMarksFormStartWhenOldIsEmpty(): void
    {
        $this->flash
            ->method('take')
            ->willReturnCallback(
                function (string $key, mixed $default = null): mixed {
                    return match ($key) {
                        'old'            => [],
                        'security_flags' => [],
                        default          => $default,
                    };
                }
            );

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::RESEND_CONFIRM);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::RESEND_CONFIRM)
            ->willReturn('csrf-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('hp_resend');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/resend-confirmation.html.twig',
                $this->callback(function (array $data): bool {
                    return $data['title']              === 'Resend confirmation'
                        && $data['csrf_token']         === 'csrf-token'
                        && $data['old']                === []
                        && $data['honeypot_name']      === 'hp_resend'
                        && $data['turnstile_required'] === false
                        && $data['turnstile_enabled']  === false;
                })
            );

        $this->handler->handle();
    }

    public function testHandleDoesNotMarkFormStartWhenOldExists(): void
    {
        $old = [
            'email' => 'john@example.com',
        ];

        $this->flash
            ->method('take')
            ->willReturnCallback(
                function (string $key, mixed $default = null) use ($old): mixed {
                    return match ($key) {
                        'old'            => $old,
                        'security_flags' => [],
                        default          => $default,
                    };
                }
            );

        $this->submissionDelay
            ->expects($this->never())
            ->method('markFormStart');

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->willReturn('csrf-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('hp_resend');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/resend-confirmation.html.twig',
                $this->callback(function (array $data) use ($old): bool {
                    return $data['old'] === $old;
                })
            );

        $this->handler->handle();
    }

    public function testHandleEnablesTurnstileWhenSecurityFlagIsPresent(): void
    {
        $this->flash
            ->method('take')
            ->willReturnCallback(
                function (string $key, mixed $default = null): mixed {
                    return match ($key) {
                        'old'            => [],
                        'security_flags' => [
                            'turnstile_resend' => true,
                        ],
                        default => $default,
                    };
                }
            );

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart');

        $this->csrf
            ->method('generateToken')
            ->willReturn('csrf-token');

        $this->honeypot
            ->method('fieldName')
            ->willReturn('hp_resend');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/resend-confirmation.html.twig',
                $this->callback(function (array $data): bool {
                    return $data['turnstile_required'] === true
                        && $data['turnstile_enabled']  === true;
                })
            );

        $this->handler->handle();
    }

    public function testHandleUsesEmptyOldWhenFlashOldIsNotArray(): void
    {
        $this->flash
            ->method('take')
            ->willReturnCallback(
                function (string $key, mixed $default = null): mixed {
                    return match ($key) {
                        'old'            => 'invalid-old',
                        'security_flags' => [],
                        default          => $default,
                    };
                }
            );

        $this->submissionDelay
            ->expects($this->never())
            ->method('markFormStart');

        $this->csrf
            ->method('generateToken')
            ->willReturn('csrf-token');

        $this->honeypot
            ->method('fieldName')
            ->willReturn('hp_resend');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/resend-confirmation.html.twig',
                $this->callback(function (array $data): bool {
                    return $data['old'] === [];
                })
            );

        $this->handler->handle();
    }
}
