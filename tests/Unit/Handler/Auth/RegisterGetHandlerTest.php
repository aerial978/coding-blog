<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Handler\Auth\RegisterGetHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterGetHandlerTest extends TestCase
{
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotValidatorInterface&MockObject $honeypot;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelay;

    private RegisterGetHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flash           = $this->createMock(FlashInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);
        $this->csrf            = $this->createMock(CsrfTokenInterface::class);
        $this->honeypot        = $this->createMock(HoneypotValidatorInterface::class);
        $this->submissionDelay = $this->createMock(SubmissionDelayValidatorInterface::class);

        $this->handler = new RegisterGetHandler(
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypot,
            $this->submissionDelay,
        );
    }

    public function testHandleRendersFormModeAndMarksFormStartWhenOldIsEmpty(): void
    {
        $this->flash
            ->method('take')
            ->willReturnCallback(function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'old'            => [],
                    'register_state' => null,
                    default          => $default,
                };
            });

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::REGISTER);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::REGISTER)
            ->willReturn('csrf-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('hp_register');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/register.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertSame('User registration', $data['title']);
                    $this->assertSame('form', $data['mode']);
                    $this->assertNull($data['obfuscated_email']);
                    $this->assertSame('csrf-token', $data['csrf_token']);
                    $this->assertSame([], $data['old']);
                    $this->assertSame('hp_register', $data['honeypot_name']);

                    $this->assertArrayNotHasKey('flashes', $data);
                    $this->assertArrayNotHasKey('show_header', $data);
                    $this->assertArrayNotHasKey('turnstile_site_key', $data);

                    return true;
                })
            );

        $this->handler->handle();
    }

    public function testHandleDoesNotMarkFormStartWhenOldExists(): void
    {
        $old = [
            'email'    => 'john@example.com',
            'username' => 'john',
        ];

        $this->flash
            ->method('take')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($old): mixed {
                return match ($key) {
                    'old'            => $old,
                    'register_state' => null,
                    default          => $default,
                };
            });

        $this->submissionDelay
            ->expects($this->never())
            ->method('markFormStart');

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::REGISTER)
            ->willReturn('csrf-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('hp_register');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/register.html.twig',
                $this->callback(function (array $data) use ($old): bool {
                    $this->assertSame('form', $data['mode']);
                    $this->assertSame($old, $data['old']);
                    $this->assertNull($data['obfuscated_email']);

                    return true;
                })
            );

        $this->handler->handle();
    }

    public function testHandleRendersCheckEmailModeWithObfuscatedEmailWhenStateExists(): void
    {
        $state = [
            'email' => 'john@example.com',
        ];

        $this->flash
            ->method('take')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($state): mixed {
                return match ($key) {
                    'old'            => [],
                    'register_state' => $state,
                    default          => $default,
                };
            });

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::REGISTER);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::REGISTER)
            ->willReturn('csrf-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('hp_register');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/register.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertSame('check_email', $data['mode']);
                    $this->assertSame('j***@example.com', $data['obfuscated_email']);

                    return true;
                })
            );

        $this->handler->handle();
    }

    public function testHandleUsesEmptyOldWhenOldFlashIsInvalid(): void
    {
        $this->flash
            ->method('take')
            ->willReturnCallback(function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'old'            => 'invalid-old',
                    'register_state' => null,
                    default          => $default,
                };
            });

        $this->submissionDelay
            ->expects($this->never())
            ->method('markFormStart');

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::REGISTER)
            ->willReturn('csrf-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('hp_register');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/register.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertSame([], $data['old']);

                    return true;
                })
            );

        $this->handler->handle();
    }
}
