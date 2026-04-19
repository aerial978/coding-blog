<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Security\Contract\AccountConfirmationServiceInterface;
use App\Service\Security\Contract\ConfirmationResendServiceInterface;
use App\Service\Security\Contract\ForgotPasswordServiceInterface;
use App\Service\Security\Contract\LoginServiceInterface;
use App\Service\Security\Contract\LogoutServiceInterface;
use App\Service\Security\Contract\RegistrationServiceInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use App\Service\Security\SecurityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SecurityServiceTest extends TestCase
{
    private RegistrationServiceInterface&MockObject $registration;
    private AccountConfirmationServiceInterface&MockObject $accountConfirmation;
    private ConfirmationResendServiceInterface&MockObject $confirmationResend;
    private LoginServiceInterface&MockObject $login;
    private LogoutServiceInterface&MockObject $logout;
    private ForgotPasswordServiceInterface&MockObject $forgotPassword;
    private ResetPasswordServiceInterface&MockObject $resetPassword;

    private SecurityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registration        = $this->createMock(RegistrationServiceInterface::class);
        $this->accountConfirmation = $this->createMock(AccountConfirmationServiceInterface::class);
        $this->confirmationResend  = $this->createMock(ConfirmationResendServiceInterface::class);
        $this->login               = $this->createMock(LoginServiceInterface::class);
        $this->logout              = $this->createMock(LogoutServiceInterface::class);
        $this->forgotPassword      = $this->createMock(ForgotPasswordServiceInterface::class);
        $this->resetPassword       = $this->createMock(ResetPasswordServiceInterface::class);

        $this->service = new SecurityService(
            $this->registration,
            $this->accountConfirmation,
            $this->confirmationResend,
            $this->login,
            $this->logout,
            $this->forgotPassword,
            $this->resetPassword,
        );
    }

    public function testRegisterDelegatesToRegistrationService(): void
    {
        $form     = ['email' => 'user@example.com'];
        $expected = ['ok' => true];

        $this->registration
            ->expects($this->once())
            ->method('register')
            ->with($form)
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->register($form));
    }

    public function testConfirmAccountDelegatesToAccountConfirmationService(): void
    {
        $token    = 'token-123';
        $expected = ['ok' => true];

        $this->accountConfirmation
            ->expects($this->once())
            ->method('confirm')
            ->with($token)
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->confirmAccount($token));
    }

    public function testResendConfirmationDelegatesToConfirmationResendService(): void
    {
        $email    = 'user@example.com';
        $expected = ['ok' => true];

        $this->confirmationResend
            ->expects($this->once())
            ->method('resend')
            ->with($email)
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->resendConfirmation($email));
    }

    public function testLoginDelegatesToLoginService(): void
    {
        $form = [
            'identifier' => 'user@example.com',
            'password'   => 'Password123!',
        ];
        $expected = ['ok' => true];

        $this->login
            ->expects($this->once())
            ->method('login')
            ->with($form)
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->login($form));
    }

    public function testLogoutDelegatesToLogoutService(): void
    {
        $this->logout
            ->expects($this->once())
            ->method('logout');

        $this->service->logout();

        $this->assertTrue(true);
    }

    public function testForgotPasswordDelegatesToForgotPasswordService(): void
    {
        $identifier = 'user@example.com';
        $expected   = ['ok' => true];

        $this->forgotPassword
            ->expects($this->once())
            ->method('requestReset')
            ->with($identifier)
            ->willReturn($expected);

        $this->assertSame($expected, $this->service->forgotPassword($identifier));
    }

    public function testResetPasswordDelegatesToResetPasswordService(): void
    {
        $token    = 'reset-token';
        $password = 'Password123!';
        $confirm  = 'Password123!';
        $expected = ['ok' => true];

        $this->resetPassword
            ->expects($this->once())
            ->method('resetPassword')
            ->with($token, $password, $confirm)
            ->willReturn($expected);

        $this->assertSame(
            $expected,
            $this->service->resetPassword($token, $password, $confirm)
        );
    }
}
