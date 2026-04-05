<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Service\Security\Contract\AccountConfirmationServiceInterface;
use App\Service\Security\Contract\ConfirmationResendServiceInterface;
use App\Service\Security\Contract\ForgotPasswordServiceInterface;
use App\Service\Security\Contract\LoginServiceInterface;
use App\Service\Security\Contract\RegistrationServiceInterface;
use App\Service\Security\Contract\ResetPasswordServiceInterface;
use App\Service\Security\Contract\SecurityServiceInterface;

final class SecurityService implements SecurityServiceInterface
{
    public function __construct(
        private RegistrationServiceInterface $registration,
        private AccountConfirmationServiceInterface $accountConfirmation,
        private ConfirmationResendServiceInterface $confirmationResend,
        private LoginServiceInterface $login,
        private ForgotPasswordServiceInterface $forgotPassword,
        private ResetPasswordServiceInterface $resetPassword,
    ) {
    }

    public function register(array $form): array
    {
        return $this->registration->register($form);
    }

    public function confirmAccount(string $token): array
    {
        return $this->accountConfirmation->confirm($token);
    }

    public function resendConfirmation(string $email): array
    {
        return $this->confirmationResend->resend($email);
    }

    public function login(array $form): array
    {
        return $this->login->login($form);
    }

    public function forgotPassword(string $identifier): array
    {
        return $this->forgotPassword->requestReset($identifier);
    }

    public function resetPassword(string $token, string $password, string $confirm): array
    {
        return $this->resetPassword->resetPassword($token, $password, $confirm);
    }
}
