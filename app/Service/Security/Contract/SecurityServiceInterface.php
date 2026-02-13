<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

/**
 * Defines the contract for user security and authentication services.
 *
 * This interface standardizes key user-related security operations such as:
 *  - Account registration
 *  - Email confirmation
 *  - Resending confirmation tokens
 *
 * Implementations must handle validation, persistence, and communication logic
 * (e.g., email sending) in a secure and consistent manner.
 */
interface SecurityServiceInterface
{
    /**
     * Handles user registration.
     *
     * Validates form input, creates a new user account, generates a confirmation
     * token, and sends a verification email.
     *
     * @param array<string, mixed> $form
     *     The associative array containing user-submitted registration data.
     *
     * @return array<string, mixed>
     *     An associative array containing registration results or errors.
     */
    public function register(array $form): array;
    /**
         * Confirms a user account using a provided confirmation token.
         *
         * Validates the token, activates the user if valid and unexpired,
         * and returns the operation result.
         *
         * @param string $token
         *     The confirmation token submitted by the user (URL parameter or form field).
         *
         * @return array<string, mixed>
         *     An associative array containing confirmation results or error details.
         */
    public function confirmAccount(string $token): array;
    /**
         * Resends a confirmation email for inactive user accounts.
         *
         * If the user’s account is not yet activated, this method regenerates
         * a confirmation token and sends a new email to the registered address.
         *
         * @param string $email
         *     The user’s email address.
         *
         * @return array<string, mixed>
         *     An associative array containing operation results or error messages.
         */
    public function resendConfirmation(string $email): array;

    public function login (array $form): array;
}
