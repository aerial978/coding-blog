<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

use App\Model\Entity\UserEntity;

/**
 * Contract for email-based two-factor authentication (2FA).
 *
 * This service handles the business logic of the second authentication step
 * after successful username/email + password validation.
 *
 * Responsibilities include:
 * - generating a temporary one-time verification code
 * - hashing and securely storing the code
 * - invalidating previous active challenges
 * - sending the verification code by email
 * - verifying submitted codes
 * - handling invalid / expired / brute-force scenarios
 */
interface Email2faServiceInterface
{
    /**
     * Generates a new 2FA verification code and sends it by email.
     *
     * Flow:
     * - invalidate previous active challenges
     * - generate secure temporary code
     * - hash the code
     * - persist the challenge
     * - send the code by email
     *
     * @param UserEntity $user
     *     The authenticated user requiring 2FA verification.
     *
     * @return bool
     *     True if the challenge was successfully created and the email sent,
     *     false otherwise.
     */
    public function generateAndSendCode(UserEntity $user): bool;

    /**
     * Resends a new 2FA verification code.
     *
     * Usually implemented by invalidating the current active code
     * and generating a fresh one.
     *
     * @param UserEntity $user
     *     The user requesting a new code.
     *
     * @return bool
     *     True if resend succeeded, false otherwise.
     */
    public function resendCode(UserEntity $user): bool;

    /**
     * Verifies a submitted 2FA code.
     *
     * This method checks:
     * - active challenge existence
     * - expiration validity
     * - maximum attempts limit
     * - secure hash comparison
     *
     * If verification succeeds:
     * - the challenge is marked as used
     *
     * @param int $userId
     *     The related user identifier.
     *
     * @param string $submittedCode
     *     The code entered by the user.
     *
     * @return string
     *     One of:
     *     - Email2faService::VERIFY_SUCCESS
     *     - Email2faService::VERIFY_INVALID
     *     - Email2faService::VERIFY_TOO_MANY_ATTEMPTS
     */
    public function verifyCode(int $userId, string $submittedCode): string;
}