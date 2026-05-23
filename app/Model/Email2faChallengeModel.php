<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Contract\Email2faChallengeModelInterface;
use App\Model\Entity\Email2faChallengeEntity;

/**
 * Handles database operations for email 2FA challenges.
 *
 * This model is responsible for persisting and retrieving temporary
 * email-based two-factor authentication challenges.
 *
 * It manages:
 * - challenge creation
 * - active challenge lookup
 * - attempts increment
 * - marking challenges as used
 * - invalidating previous active challenges
 * - resend tracking
 */
class Email2faChallengeModel implements Email2faChallengeModelInterface
{
    protected string $table = 'user_2fa_challenge';

    public function __construct(
        private readonly SqlHelperInterface $sqlHelper,
    ) {
    }

    /**
     * Creates a new email 2FA challenge.
     */
    public function createChallenge(
        int $userId,
        string $codeHash,
        string $expiresAt
    ): int {
        $sql = "INSERT INTO {$this->table} (
                    user_id,
                    code_hash,
                    expires_at,
                    used,
                    attempts,
                    created_at,
                    last_sent_at
                ) VALUES (
                    :user_id,
                    :code_hash,
                    :expires_at,
                    0,
                    0,
                    NOW(),
                    NOW()
                )";

        $params = [
            ':user_id'    => $userId,
            ':code_hash'  => $codeHash,
            ':expires_at' => $expiresAt,
        ];

        $query = $this->sqlHelper->request($sql, $params);

        if ($query->rowCount() !== 1) {
            return 0;
        }

        return $this->sqlHelper->lastInsertId();
    }

    /**
     * Returns the current active challenge for a user.
     */
    public function findActiveChallengeByUserId(int $userId): ?Email2faChallengeEntity
    {
        $sql = "SELECT
                    id,
                    user_id,
                    code_hash,
                    expires_at,
                    used,
                    used_at,
                    attempts,
                    created_at,
                    last_sent_at
                FROM {$this->table}
                WHERE user_id = :user_id
                  AND used = 0
                  AND expires_at > NOW()
                ORDER BY created_at DESC
                LIMIT 1";

        $stmt = $this->sqlHelper->request($sql, [
            ':user_id' => $userId,
        ]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row
            ? (new Email2faChallengeEntity())->hydrate((array) $row)
            : null;
    }

    /**
     * Increments the verification attempts counter.
     */
    public function incrementAttempts(int $challengeId): bool
    {
        $sql = "UPDATE {$this->table}
                SET attempts = attempts + 1
                WHERE id = :id";

        $query = $this->sqlHelper->request($sql, [
            ':id' => $challengeId,
        ]);

        return $query->rowCount() === 1;
    }

    /**
     * Marks a challenge as used.
     */
    public function markAsUsed(int $challengeId): bool
    {
        $sql = "UPDATE {$this->table}
                SET
                    used = 1,
                    used_at = NOW()
                WHERE id = :id";

        $query = $this->sqlHelper->request($sql, [
            ':id' => $challengeId,
        ]);

        return $query->rowCount() === 1;
    }

    public function invalidateActiveChallengesForUser(int $userId): bool
    {
        $sql = "UPDATE {$this->table}
                SET
                    used = 1,
                    used_at = NOW()
                WHERE user_id = :user_id
                AND used = 0";

        $this->sqlHelper->request($sql, [
            ':user_id' => $userId,
        ]);

        return true;
    }

    /**
     * Updates resend tracking timestamp.
     */
    public function updateLastSentAt(int $challengeId): bool
    {
        $sql = "UPDATE {$this->table}
                SET last_sent_at = NOW()
                WHERE id = :id";

        $query = $this->sqlHelper->request($sql, [
            ':id' => $challengeId,
        ]);

        return $query->rowCount() === 1;
    }
}
