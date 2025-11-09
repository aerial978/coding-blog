<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Service\Security\Contract\SecurityServiceInterface;

/**
 * Implements user registration, account confirmation, and confirmation-resend flows.
 *
 * This service coordinates validation, persistence, token generation/storage, and
 * email delivery around user onboarding. It is transaction-aware for operations
 * that must be committed atomically (e.g., user creation + token insertion).
 */
final class SecurityService implements SecurityServiceInterface
{
    private RegistrationService $registration;
    private AccountConfirmationService $accountConfirmation;
    private ConfirmationResendService $confirmationResend;

    public function __construct(
        RegistrationService $registration,
        AccountConfirmationService $accountConfirmation,
        ConfirmationResendService $confirmationResend,
    ) {

        $this->registration         = $registration;
        $this->accountConfirmation  = $accountConfirmation;
        $this->confirmationResend   = $confirmationResend;
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
}
