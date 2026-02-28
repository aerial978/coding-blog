<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Service\Security\Contract\AccountConfirmationServiceInterface;
use App\Service\Security\Contract\ConfirmationResendServiceInterface;
use App\Service\Security\Contract\RegistrationServiceInterface;
use App\Service\Security\Contract\SecurityServiceInterface;
use App\Service\Security\Contract\LoginServiceInterface;
use App\Service\Security\Contract\ForgotPasswordServiceInterface;

final class SecurityService implements SecurityServiceInterface
{
    public function __construct(
        private RegistrationServiceInterface $registration,
        private AccountConfirmationServiceInterface $accountConfirmation,
        private ConfirmationResendServiceInterface $confirmationResend,
        private LoginServiceInterface $login,
        private ForgotPasswordServiceInterface $forgotPassword,
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
}
