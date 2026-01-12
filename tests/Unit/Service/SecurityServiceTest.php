<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Security\Contract\AccountConfirmationServiceInterface;
use App\Service\Security\Contract\ConfirmationResendServiceInterface;
use App\Service\Security\Contract\RegistrationServiceInterface;
use App\Service\Security\SecurityService;
use PHPUnit\Framework\TestCase;

final class SecurityServiceTest extends TestCase
{
    public function testRegisterDelegatesToRegistrationService(): void
    {
        $form = ['email' => 'user@example.com'];

        $registration        = $this->createMock(RegistrationServiceInterface::class);
        $accountConfirmation = $this->createMock(AccountConfirmationServiceInterface::class);
        $confirmationResend  = $this->createMock(ConfirmationResendServiceInterface::class);

        $expected = ['ok' => true];

        $registration->expects(self::once())
            ->method('register')
            ->with($form)
            ->willReturn($expected);

        $service = new SecurityService($registration, $accountConfirmation, $confirmationResend);

        self::assertSame($expected, $service->register($form));
    }

    public function testConfirmAccountDelegatesToAccountConfirmationService(): void
    {
        $token = 'token-123';

        $registration        = $this->createMock(RegistrationServiceInterface::class);
        $accountConfirmation = $this->createMock(AccountConfirmationServiceInterface::class);
        $confirmationResend  = $this->createMock(ConfirmationResendServiceInterface::class);

        $expected = ['ok' => true];

        $accountConfirmation->expects(self::once())
            ->method('confirm')
            ->with($token)
            ->willReturn($expected);

        $service = new SecurityService($registration, $accountConfirmation, $confirmationResend);

        self::assertSame($expected, $service->confirmAccount($token));
    }

    public function testResendConfirmationDelegatesToConfirmationResendService(): void
    {
        $email = 'user@example.com';

        $registration        = $this->createMock(RegistrationServiceInterface::class);
        $accountConfirmation = $this->createMock(AccountConfirmationServiceInterface::class);
        $confirmationResend  = $this->createMock(ConfirmationResendServiceInterface::class);

        $expected = ['ok' => true];

        $confirmationResend->expects(self::once())
            ->method('resend')
            ->with($email)
            ->willReturn($expected);

        $service = new SecurityService($registration, $accountConfirmation, $confirmationResend);

        self::assertSame($expected, $service->resendConfirmation($email));
    }
}
