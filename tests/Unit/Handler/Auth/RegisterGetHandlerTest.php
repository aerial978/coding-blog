<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\View;
use App\Handler\Auth\RegisterGetHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterGetHandlerTest extends TestCase
{
    private View&MockObject $view;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotValidatorInterface&MockObject $honeypot;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelay;

    private RegisterGetHandler $handler;

    private mixed $originalTurnstileSiteKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTurnstileSiteKey = $_ENV['TURNSTILE_SITE_KEY'] ?? null;

        $this->view            = $this->createMock(View::class);
        $this->flash           = $this->createMock(FlashInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);
        $this->csrf            = $this->createMock(CsrfTokenInterface::class);
        $this->honeypot        = $this->createMock(HoneypotValidatorInterface::class);
        $this->submissionDelay = $this->createMock(SubmissionDelayValidatorInterface::class);

        $this->handler = new RegisterGetHandler(
            $this->view,
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypot,
            $this->submissionDelay,
        );
    }

    protected function tearDown(): void
    {
        if ($this->originalTurnstileSiteKey === null) {
            unset($_ENV['TURNSTILE_SITE_KEY']);
        } else {
            $_ENV['TURNSTILE_SITE_KEY'] = $this->originalTurnstileSiteKey;
        }

        parent::tearDown();
    }

    public function testHandleRendersFormModeAndMarksFormStartWhenOldIsEmpty(): void
    {
        $_ENV['TURNSTILE_SITE_KEY'] = '  site-key-123  ';

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
            ->with('register');

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
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
                    return $data['title']              === 'User registration'
                        && $data['mode']               === 'form'
                        && $data['obfuscated_email']   === null
                        && $data['csrf_token']         === 'csrf-token'
                        && $data['old']                === []
                        && $data['honeypot_name']      === 'hp_register'
                        && $data['turnstile_site_key'] === 'site-key-123';
                })
            );

        $this->handler->handle();
    }

    public function testHandleDoesNotMarkFormStartWhenOldExists(): void
    {
        $_ENV['TURNSTILE_SITE_KEY'] = 'site-key';

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
                    return $data['mode']             === 'form'
                        && $data['old']              === $old
                        && $data['obfuscated_email'] === null;
                })
            );

        $this->handler->handle();
    }

    public function testHandleRendersCheckEmailModeWithObfuscatedEmailWhenStateExists(): void
    {
        $_ENV['TURNSTILE_SITE_KEY'] = 'site-key';

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
            ->with('register');

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
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
                    return $data['mode']             === 'check_email'
                        && $data['obfuscated_email'] === 'j***@example.com';
                })
            );

        $this->handler->handle();
    }

    public function testHandleUsesEmptyTurnstileSiteKeyWhenEnvIsMissing(): void
    {
        unset($_ENV['TURNSTILE_SITE_KEY']);

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
            ->with('register');

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
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
                    return $data['turnstile_site_key'] === '';
                })
            );

        $this->handler->handle();
    }
}
