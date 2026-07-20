<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\Contract\RateLimiterInterface;
use App\Core\ErrorCode;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\RateLimitGuard;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RateLimitGuardTest extends TestCase
{
    private RateLimiterFactoryInterface&MockObject $rateLimiterFactory;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;

    private RateLimitGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $this->flash              = $this->createMock(FlashInterface::class);
        $this->responder          = $this->createMock(ResponderInterface::class);

        $this->guard = new RateLimitGuard(
            $this->rateLimiterFactory,
            $this->flash,
            $this->responder,
        );
    }

    public function testAssertAllowedReturnsTrueAndRecordsAttemptWhenAllowed(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('isAllowed')
            ->willReturn(true);

        $limiter
            ->expects($this->once())
            ->method('recordAttempt');

        $limiter
            ->expects($this->never())
            ->method('getRetryAfter');

        $this->rateLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->with('register:127.0.0.1', 5, 60)
            ->willReturn($limiter);

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->flash
            ->expects($this->never())
            ->method('take');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $result = $this->guard->assertAllowed([
            'key'        => 'register:127.0.0.1',
            'limit'      => 5,
            'window_sec' => 60,
            'redirect'   => '/coding-blog/register',
        ]);

        $this->assertTrue($result);
    }

    public function testAssertAllowedReturnsFalseAndUsesDefaultBehaviorWhenBlocked(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('isAllowed')
            ->willReturn(false);

        $limiter
            ->expects($this->never())
            ->method('recordAttempt');

        $limiter
            ->expects($this->once())
            ->method('getRetryAfter')
            ->willReturn(75);

        $this->rateLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->with('login:127.0.0.1', 3, 120)
            ->willReturn($limiter);

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
            ->with('/coding-blog/login');

        $result = $this->guard->assertAllowed([
            'key'        => 'login:127.0.0.1',
            'limit'      => 3,
            'window_sec' => 120,
            'redirect'   => '/coding-blog/login',
        ]);

        $this->assertFalse($result);
    }

    public function testAssertAllowedStoresOldInputWhenBlockedInDefaultMode(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('isAllowed')
            ->willReturn(false);

        $limiter
            ->expects($this->never())
            ->method('recordAttempt');

        $limiter
            ->expects($this->once())
            ->method('getRetryAfter')
            ->willReturn(12);

        $old = [
            'identifier' => 'john@example.com',
        ];

        $this->rateLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

        $this->flash
            ->expects($this->never())
            ->method('take');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isString());

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', $old);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/login');

        $result = $this->guard->assertAllowed([
            'key'        => 'login:127.0.0.1',
            'limit'      => 3,
            'window_sec' => 120,
            'redirect'   => '/coding-blog/login',
            'put_old'    => $old,
        ]);

        $this->assertFalse($result);
    }

    public function testAssertAllowedStoresSecurityFlagsWhenSetFlagsIsProvided(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('isAllowed')
            ->willReturn(false);

        $limiter
            ->expects($this->never())
            ->method('recordAttempt');

        $limiter
            ->expects($this->once())
            ->method('getRetryAfter')
            ->willReturn(30);

        $this->rateLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

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
                    'rate_limited'       => true,
                ]
            );

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isString());

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $result = $this->guard->assertAllowed([
            'key'        => 'register:127.0.0.1',
            'limit'      => 2,
            'window_sec' => 60,
            'redirect'   => '/coding-blog/register',
            'set_flags'  => [
                'rate_limited' => true,
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertAllowedUsesCustomFlagsBagWhenProvided(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('isAllowed')
            ->willReturn(false);

        $limiter
            ->expects($this->never())
            ->method('recordAttempt');

        $limiter
            ->expects($this->once())
            ->method('getRetryAfter')
            ->willReturn(10);

        $this->rateLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

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
            ->with('error', $this->isString());

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $result = $this->guard->assertAllowed([
            'key'        => 'forgot:127.0.0.1',
            'limit'      => 3,
            'window_sec' => 60,
            'redirect'   => '/coding-blog/forgot-password',
            'flags_bag'  => 'custom_flags',
            'set_flags'  => [
                'needs_challenge' => true,
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertAllowedUsesSilentSuccessModeWhenBlocked(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('isAllowed')
            ->willReturn(false);

        $limiter
            ->expects($this->never())
            ->method('recordAttempt');

        $limiter
            ->expects($this->once())
            ->method('getRetryAfter')
            ->willReturn(90);

        $this->rateLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

        $this->flash
            ->expects($this->never())
            ->method('take');

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'success',
                $this->isString()
            );

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $result = $this->guard->assertAllowed([
            'key'            => 'resend:127.0.0.1',
            'limit'          => 2,
            'window_sec'     => 300,
            'redirect'       => '/coding-blog/resend-confirmation',
            'silent_success' => true,
        ]);

        $this->assertFalse($result);
    }

    public function testAssertAllowedUsesSilentSuccessAndPutOldWhenProvided(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);

        $limiter
            ->expects($this->once())
            ->method('isAllowed')
            ->willReturn(false);

        $limiter
            ->expects($this->never())
            ->method('recordAttempt');

        $limiter
            ->expects($this->once())
            ->method('getRetryAfter')
            ->willReturn(15);

        $old = [
            'email' => 'john@example.com',
        ];

        $this->rateLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'info',
                $this->isString()
            );

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', $old);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/resend-confirmation');

        $result = $this->guard->assertAllowed([
            'key'                       => 'resend:127.0.0.1',
            'limit'                     => 2,
            'window_sec'                => 300,
            'redirect'                  => '/coding-blog/resend-confirmation',
            'silent_success'            => true,
            'silent_success_flash_type' => 'info',
            'silent_success_code'       => ErrorCode::AUTH_RESEND_EMAIL_SENT,
            'put_old'                   => $old,
        ]);

        $this->assertFalse($result);
    }
}
