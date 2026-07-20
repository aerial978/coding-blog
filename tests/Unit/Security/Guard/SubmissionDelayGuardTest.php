<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;
use App\Security\Guard\SubmissionDelayGuard;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubmissionDelayGuardTest extends TestCase
{
    private SubmissionDelayValidatorInterface&MockObject $submissionDelay;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;

    private SubmissionDelayGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->submissionDelay = $this->createMock(SubmissionDelayValidatorInterface::class);
        $this->flash           = $this->createMock(FlashInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);

        $this->guard = new SubmissionDelayGuard(
            $this->submissionDelay,
            $this->flash,
            $this->responder,
            new LogContextNormalizer(),
        );
    }

    public function testAssertPassedReturnsTrueWhenDelayIsValid(): void
    {
        $this->submissionDelay
            ->expects($this->once())
            ->method('assertDelayPassed')
            ->with('register', null, null);

        $this->flash
            ->expects($this->never())
            ->method('take');

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $result = $this->guard->assertPassed([
            'form_id'  => 'register',
            'redirect' => '/coding-blog/register',
        ]);

        $this->assertTrue($result);
    }

    public function testAssertPassedUsesMinAndMaxWhenProvided(): void
    {
        $this->submissionDelay
            ->expects($this->once())
            ->method('assertDelayPassed')
            ->with('reset_password', 5, 120);

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $result = $this->guard->assertPassed([
            'form_id'  => 'reset_password',
            'redirect' => '/coding-blog/reset-password',
            'min_sec'  => '5',
            'max_sec'  => 120,
        ]);

        $this->assertTrue($result);
    }

    public function testAssertPassedReturnsFalseAndRedirectsWhenSubmissionIsTooFastUsingDefaultPolicy(): void
    {
        $exception = new SuspiciousSubmissionException(
            'too_fast',
            [
                'form'    => 'register',
                'elapsed' => 1,
                'min'     => 5,
            ]
        );

        $this->submissionDelay
            ->expects($this->once())
            ->method('assertDelayPassed')
            ->with('register', null, null)
            ->willThrowException($exception);

        $this->flash
            ->expects($this->never())
            ->method('take');

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                $this->isString()
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $result = $this->guard->assertPassed([
            'form_id'  => 'register',
            'redirect' => '/coding-blog/register',
            'context'  => [
                'route' => 'register',
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertPassedUsesCustomPolicyForKnownReason(): void
    {
        $exception = new SuspiciousSubmissionException(
            'too_slow',
            [
                'form'    => 'register',
                'elapsed' => 5000,
                'max'     => 1800,
            ]
        );

        $this->submissionDelay
            ->expects($this->once())
            ->method('assertDelayPassed')
            ->with('register', null, null)
            ->willThrowException($exception);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'warning',
                $this->isString()
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $result = $this->guard->assertPassed([
            'form_id'  => 'register',
            'redirect' => '/coding-blog/register',
            'policy'   => [
                'too_slow' => [
                    'flash' => 'warning',
                    'code'  => ErrorCode::AUTH_TECHNICAL_ERROR,
                ],
            ],
            'default' => [
                'flash' => 'error',
                'code'  => ErrorCode::AUTH_TECHNICAL_ERROR,
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertPassedStoresFlagsWhenSetFlagsIsProvided(): void
    {
        $exception = new SuspiciousSubmissionException(
            'too_fast',
            [
                'form'    => 'login',
                'elapsed' => 0,
                'min'     => 3,
            ]
        );

        $this->submissionDelay
            ->expects($this->once())
            ->method('assertDelayPassed')
            ->with('login', null, null)
            ->willThrowException($exception);

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
                    'delay_failed'       => true,
                ]
            );

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                $this->isString()
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $result = $this->guard->assertPassed([
            'form_id'   => 'login',
            'redirect'  => '/coding-blog/login',
            'set_flags' => [
                'delay_failed' => true,
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertPassedUsesCustomFlagsBagWhenProvided(): void
    {
        $exception = new SuspiciousSubmissionException(
            'too_fast',
            [
                'form'    => 'forgot_password',
                'elapsed' => 1,
                'min'     => 4,
            ]
        );

        $this->submissionDelay
            ->expects($this->once())
            ->method('assertDelayPassed')
            ->with('forgot_password', null, null)
            ->willThrowException($exception);

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
                    'needs_challenge' => true,
                ]
            );

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                $this->isString()
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $result = $this->guard->assertPassed([
            'form_id'   => 'forgot_password',
            'redirect'  => '/coding-blog/forgot-password',
            'flags_bag' => 'custom_flags',
            'set_flags' => [
                'needs_challenge' => true,
            ],
        ]);

        $this->assertFalse($result);
    }
}
