<?php

declare(strict_types=1);

namespace App\Model\Contract;

use App\Model\Entity\Email2faChallengeEntity;

/**
 * Contract for email 2FA challenge persistence.
 *
 * This interface defines the required operations for managing
 * temporary email-based two-factor authentication challenges.
 *
 * Responsibilities include:
 * - creating a new challenge
 * - retrieving the current active challenge
 * - incrementing failed verification attempts
 * - marking a challenge as used
 * - invalidating previous active challenges
 * - updating resend tracking
 */
interface Email2faChallengeModelInterface
{
    /**
     * Creates a new email 2FA challenge.
     *
     * A secure hashed verification code is stored with
     * an expiration datetime.
     *
     * @param int $userId
     *     The related user identifier.
     *
     * @param string $codeHash
     *     The hashed verification code (never plain text).
     *
     * @param string $expiresAt
     *     The expiration datetime of the challenge.
     *
     * @return int
     *     The created challenge ID, or 0 if creation failed.
     */
    public function createChallenge(
        int $userId,
        string $codeHash,
        string $expiresAt
    ): int;

    /**
     * Returns the current active challenge for a user.
     *
     * Active means:
     * - not used
     * - not expired
     *
     * @param int $userId
     *     The related user identifier.
     *
     * @return Email2faChallengeEntity|null
     *     The active challenge if found, null otherwise.
     */
    public function findActiveChallengeByUserId(
        int $userId
    ): ?Email2faChallengeEntity;

    /**
     * Increments the failed verification attempts counter.
     *
     * Used when the user submits an invalid code.
     *
     * @param int $challengeId
     *     The challenge identifier.
     *
     * @return bool
     *     True on success, false otherwise.
     */
    public function incrementAttempts(int $challengeId): bool;

    /**
     * Marks a challenge as used.
     *
     * Prevents code reuse after successful validation.
     *
     * @param int $challengeId
     *     The challenge identifier.
     *
     * @return bool
     *     True on success, false otherwise.
     */
    public function markAsUsed(int $challengeId): bool;

    /**
     * Invalidates all active challenges for a user.
     *
     * Typically used before generating a new code,
     * so only one valid code exists at a time.
     *
     * @param int $userId
     *     The related user identifier.
     *
     * @return bool
     *     True on success, false otherwise.
     */
    public function invalidateActiveChallengesForUser(
        int $userId
    ): bool;

    /**
     * Updates the last email sending timestamp.
     *
     * Useful for resend throttling and abuse prevention.
     *
     * @param int $challengeId
     *     The challenge identifier.
     *
     * @return bool
     *     True on success, false otherwise.
     */
    public function updateLastSentAt(int $challengeId): bool;
}
