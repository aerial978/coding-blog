<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;
use App\Security\Guard\HoneypotGuard;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class HoneypotGuardTest extends TestCase
{
    private HoneypotValidatorInterface&MockObject $honeypot;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;

    private HoneypotGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->honeypot  = $this->createMock(HoneypotValidatorInterface::class);
        $this->flash     = $this->createMock(FlashInterface::class);
        $this->responder = $this->createMock(ResponderInterface::class);

        $this->guard = new HoneypotGuard(
            $this->honeypot,
            $this->flash,
            $this->responder,
            new LogContextNormalizer(),
        );
    }

    public function testAssertCleanReturnsTrueWhenFormIsClean(): void
    {
        $form = [
            'email' => 'john@example.com',
            'fax'   => '',
        ];

        $this->honeypot
            ->expects($this->once())
            ->method('assertClean')
            ->with($form);

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->flash
            ->expects($this->never())
            ->method('take');

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $result = $this->guard->assertClean([
            'form'     => $form,
            'redirect' => '/coding-blog/register',
        ]);

        $this->assertTrue($result);
    }

    public function testAssertCleanReturnsFalseAndRedirectsWhenSubmissionIsSuspicious(): void
    {
        $form = [
            'email' => 'john@example.com',
            'fax'   => 'bot-filled',
        ];

        $this->honeypot
            ->expects($this->once())
            ->method('assertClean')
            ->with($form)
            ->willThrowException(new SuspiciousSubmissionException('honeypot triggered'));

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                $this->isString()
            );

        $this->flash
            ->expects($this->never())
            ->method('take');

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $result = $this->guard->assertClean([
            'form'     => $form,
            'redirect' => '/coding-blog/register',
            'code'     => ErrorCode::AUTH_TECHNICAL_ERROR,
            'context'  => [
                'email' => 'john@example.com',
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertCleanStoresSecurityFlagsWhenSetFlagsIsProvided(): void
    {
        $form = [
            'email' => 'john@example.com',
            'fax'   => 'bot-filled',
        ];

        $this->honeypot
            ->expects($this->once())
            ->method('assertClean')
            ->with($form)
            ->willThrowException(new SuspiciousSubmissionException('honeypot triggered'));

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'warning',
                $this->isString()
            );

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('security_flags', [])
            ->willReturn([
                'turnstile_required' => true,
            ]);

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with(
                'security_flags',
                [
                    'turnstile_required' => true,
                    'honeypot_tripped'   => true,
                ]
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $result = $this->guard->assertClean([
            'form'       => $form,
            'redirect'   => '/coding-blog/login',
            'flash_type' => 'warning',
            'set_flags'  => [
                'honeypot_tripped' => true,
            ],
            'context' => [
                'route' => 'login',
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertCleanUsesCustomFlagsBagWhenProvided(): void
    {
        $form = [
            'email' => 'john@example.com',
            'fax'   => 'bot-filled',
        ];

        $this->honeypot
            ->expects($this->once())
            ->method('assertClean')
            ->willThrowException(new SuspiciousSubmissionException('honeypot triggered'));

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                $this->isString()
            );

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('custom_flags', [])
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with(
                'custom_flags',
                [
                    'needs_extra_check' => true,
                ]
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $result = $this->guard->assertClean([
            'form'      => $form,
            'redirect'  => '/coding-blog/forgot-password',
            'flags_bag' => 'custom_flags',
            'set_flags' => [
                'needs_extra_check' => true,
            ],
        ]);

        $this->assertFalse($result);
    }
}
