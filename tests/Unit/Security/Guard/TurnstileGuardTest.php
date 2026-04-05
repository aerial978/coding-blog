<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\Guard\TurnstileGuard;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TurnstileGuardTest extends TestCase
{
    private TurnstileValidatorInterface&MockObject $turnstile;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;

    private TurnstileGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->turnstile = $this->createMock(TurnstileValidatorInterface::class);
        $this->flash     = $this->createMock(FlashInterface::class);
        $this->responder = $this->createMock(ResponderInterface::class);

        $this->guard = new TurnstileGuard(
            $this->turnstile,
            $this->flash,
            $this->responder,
            new LogContextNormalizer(),
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    public function testAssertValidReturnsTrueWhenTurnstileValidationSucceedsWithProvidedIp(): void
    {
        $form = [
            'cf-turnstile-response' => 'token-ok',
        ];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('token-ok', '127.0.0.1')
            ->willReturn(true);

        $this->turnstile
            ->expects($this->never())
            ->method('getLastResponse');

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $result = $this->guard->assertValid([
            'form'     => $form,
            'redirect' => '/coding-blog/register',
            'ip'       => '127.0.0.1',
        ]);

        $this->assertTrue($result);
    }

    public function testAssertValidReadsIpFromServerWhenIpOptionIsNotProvided(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';

        $form = [
            'cf-turnstile-response' => 'token-server-ip',
        ];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('token-server-ip', '192.168.1.50')
            ->willReturn(true);

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $result = $this->guard->assertValid([
            'form'     => $form,
            'redirect' => '/coding-blog/login',
        ]);

        $this->assertTrue($result);
    }

    public function testAssertValidUsesCustomTokenFieldWhenProvided(): void
    {
        $form = [
            'custom_turnstile' => 'custom-token',
        ];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('custom-token', '10.0.0.1')
            ->willReturn(true);

        $result = $this->guard->assertValid([
            'form'        => $form,
            'redirect'    => '/coding-blog/reset-password',
            'token_field' => 'custom_turnstile',
            'ip'          => '10.0.0.1',
        ]);

        $this->assertTrue($result);
    }

    public function testAssertValidReturnsFalseAndShowsHumanMessageWhenTokenIsMissingByErrorCode(): void
    {
        $form = [
            'cf-turnstile-response' => '',
        ];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('', '127.0.0.1')
            ->willReturn(false);

        $this->turnstile
            ->expects($this->once())
            ->method('getLastResponse')
            ->willReturn([
                'error-codes' => ['missing-input-response'],
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Veuillez valider le challenge anti-robot.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/register');

        $result = $this->guard->assertValid([
            'form'     => $form,
            'redirect' => '/coding-blog/register',
            'ip'       => '127.0.0.1',
            'context'  => [
                'route' => 'register',
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertValidReturnsFalseAndShowsHumanMessageWhenTokenIsMissingByDiagnostic(): void
    {
        $form = [];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('', null)
            ->willReturn(false);

        $this->turnstile
            ->expects($this->once())
            ->method('getLastResponse')
            ->willReturn([
                'diagnostic' => 'empty_token',
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', 'Veuillez valider le challenge anti-robot.');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/coding-blog/forgot-password');

        $result = $this->guard->assertValid([
            'form'     => $form,
            'redirect' => '/coding-blog/forgot-password',
        ]);

        $this->assertFalse($result);
    }

    public function testAssertValidReturnsFalseAndShowsTechnicalMessageForOtherFailures(): void
    {
        $form = [
            'cf-turnstile-response' => 'bad-token',
        ];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('bad-token', '127.0.0.1')
            ->willReturn(false);

        $this->turnstile
            ->expects($this->once())
            ->method('getLastResponse')
            ->willReturn([
                'error-codes' => ['invalid-input-response'],
                'diagnostic'  => 'remote_validation_failed',
            ]);

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

        $result = $this->guard->assertValid([
            'form'     => $form,
            'redirect' => '/coding-blog/login',
            'ip'       => '127.0.0.1',
            'context'  => [
                'route' => 'login',
            ],
        ]);

        $this->assertFalse($result);
    }

    public function testAssertValidTrimsTokenBeforeValidation(): void
    {
        $form = [
            'cf-turnstile-response' => '   token-trimmed   ',
        ];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('token-trimmed', '127.0.0.1')
            ->willReturn(true);

        $result = $this->guard->assertValid([
            'form'     => $form,
            'redirect' => '/coding-blog/register',
            'ip'       => '127.0.0.1',
        ]);

        $this->assertTrue($result);
    }

    public function testAssertValidPassesNullIpWhenIpOptionExistsButIsNotString(): void
    {
        $form = [
            'cf-turnstile-response' => 'token-ok',
        ];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('token-ok', null)
            ->willReturn(true);

        $result = $this->guard->assertValid([
            'form'     => $form,
            'redirect' => '/coding-blog/register',
            'ip'       => 123,
        ]);

        $this->assertTrue($result);
    }

    public function testAssertValidSupportsDebugLogWithoutChangingBehavior(): void
    {
        $form = [
            'cf-turnstile-response' => 'debug-token',
        ];

        $this->turnstile
            ->expects($this->once())
            ->method('validate')
            ->with('debug-token', '127.0.0.1')
            ->willReturn(true);

        $result = $this->guard->assertValid([
            'form'      => $form,
            'redirect'  => '/coding-blog/register',
            'ip'        => '127.0.0.1',
            'debug_log' => true,
        ]);

        $this->assertTrue($result);
    }
}
