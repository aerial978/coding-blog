<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Auth;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Handler\Auth\ForgotPasswordGetHandler;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ForgotPasswordGetHandlerTest extends TestCase
{
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private CsrfTokenInterface&MockObject $csrf;
    private HoneypotValidatorInterface&MockObject $honeypot;
    private SubmissionDelayValidatorInterface&MockObject $submissionDelay;

    private ForgotPasswordGetHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flash           = $this->createMock(FlashInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);
        $this->csrf            = $this->createMock(CsrfTokenInterface::class);
        $this->honeypot        = $this->createMock(HoneypotValidatorInterface::class);
        $this->submissionDelay = $this->createMock(SubmissionDelayValidatorInterface::class);

        $this->handler = new ForgotPasswordGetHandler(
            $this->flash,
            $this->responder,
            $this->csrf,
            $this->honeypot,
            $this->submissionDelay,
        );
    }

    public function testRendersForgotPasswordPageWithFreshFormStart(): void
    {
        $this->flash
            ->expects($this->exactly(2))
            ->method('take')
            ->willReturnMap([
                ['old', [], []],
                ['security_flags', [], []],
            ]);

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::FORGOT_PASSWORD);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::FORGOT_PASSWORD)
            ->willReturn('csrf-forgot-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('fax');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/forgot-password.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertSame('Forgot password', $data['title']);

                    $this->assertSame(
                        'csrf-forgot-token',
                        $data['csrf_token']
                    );

                    $this->assertSame([], $data['old']);

                    $this->assertSame(
                        'fax',
                        $data['honeypot_name']
                    );

                    $this->assertFalse(
                        $data['turnstile_required']
                    );

                    $this->assertFalse(
                        $data['turnstile_enabled']
                    );

                    $this->assertArrayNotHasKey(
                        'flashes',
                        $data
                    );

                    $this->assertArrayNotHasKey(
                        'show_header',
                        $data
                    );

                    $this->assertArrayNotHasKey(
                        'turnstile_site_key',
                        $data
                    );

                    return true;
                })
            );

        $this->handler->handle();
    }

    public function testDoesNotRestartDelayWhenOldInputExists(): void
    {
        $old = [
            'email' => 'john@example.com',
        ];

        $this->flash
            ->expects($this->exactly(2))
            ->method('take')
            ->willReturnMap([
                ['old', [], $old],
                ['security_flags', [], []],
            ]);

        $this->submissionDelay
            ->expects($this->never())
            ->method('markFormStart');

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::FORGOT_PASSWORD)
            ->willReturn('csrf-forgot-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('fax');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/forgot-password.html.twig',
                $this->callback(function (array $data) use ($old): bool {
                    $this->assertSame($old, $data['old']);

                    return true;
                })
            );

        $this->handler->handle();
    }

    public function testEnablesTurnstileWhenSecurityFlagExists(): void
    {
        $flags = [
            'turnstile_forgot' => true,
        ];

        $this->flash
            ->expects($this->exactly(2))
            ->method('take')
            ->willReturnMap([
                ['old', [], []],
                ['security_flags', [], $flags],
            ]);

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::FORGOT_PASSWORD);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::FORGOT_PASSWORD)
            ->willReturn('csrf-forgot-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('fax');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/forgot-password.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertTrue(
                        $data['turnstile_required']
                    );

                    $this->assertTrue(
                        $data['turnstile_enabled']
                    );

                    return true;
                })
            );

        $this->handler->handle();
    }

    public function testHandlesInvalidSecurityFlagsGracefully(): void
    {
        $this->flash
            ->expects($this->exactly(2))
            ->method('take')
            ->willReturnMap([
                ['old', [], []],
                ['security_flags', [], 'invalid'],
            ]);

        $this->submissionDelay
            ->expects($this->once())
            ->method('markFormStart')
            ->with(FormId::FORGOT_PASSWORD);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->willReturn('csrf-forgot-token');

        $this->honeypot
            ->expects($this->once())
            ->method('fieldName')
            ->willReturn('fax');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'security/forgot-password.html.twig',
                $this->callback(function (array $data): bool {
                    $this->assertFalse(
                        $data['turnstile_required']
                    );

                    $this->assertFalse(
                        $data['turnstile_enabled']
                    );

                    return true;
                })
            );

        $this->handler->handle();
    }
}
