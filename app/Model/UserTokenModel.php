<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Contract\UserTokenModelInterface;
use InvalidArgumentException;

/**
 * Handles database operations related to user tokens.
 *
 * This model provides CRUD-like methods for managing confirmation and
 * authentication tokens. It is responsible for creating, updating,
 * and validating tokens that are associated with user accounts.
 *
 * It is primarily used during account confirmation, password reset,
 * and similar token-based workflows.
 */
class UserTokenModel implements UserTokenModelInterface
{
    /** @var string The database table name for user tokens */
    protected string $table = 'user_token';

    /**
     * Constructor.
     *
     * Initializes the model with a SqlHelper instance for performing
     * parameterized SQL queries safely and consistently.
     *
     * @param SqlHelperInterface $sqlHelper
     *     Helper utility for preparing and executing SQL queries.
     */
    public function __construct(
        private SqlHelperInterface $sqlHelper
    ) {
    }

    /* ============================================================
     * Generic internals
     * ============================================================
     */

    /**
     * Creates or updates a token for a given user and type.
     *
     * @param int $userId
     * @param string $purpose
     * @param string $hashBinary32
     * @param \DateTimeInterface $expiresAt
     *
     * @return bool
     */
    private function createToken(
        int $userId,
        string $purpose,
        string $hashBinary32,
        \DateTimeInterface $expiresAt
    ): bool {
        if (strlen($hashBinary32) !== 32) {
            throw new InvalidArgumentException('Token hash must be exactly 32 bytes (SHA-256).');
        }

        $sql = "
            INSERT INTO {$this->table}
                (user_id, purpose, token_hash, expires_at, used, created_at)
            VALUES
                (:user_id, :purpose, :token_hash, :expires_at, 0, NOW())
            ON DUPLICATE KEY UPDATE
                token_hash = VALUES(token_hash),
                expires_at = VALUES(expires_at),
                used       = 0,
                used_at    = NULL,
                created_at = VALUES(created_at)
        ";

        $params = [
            ':user_id'       => $userId,
            ':purpose'       => $purpose,
            ':token_hash'    => $hashBinary32,
            ':expires_at'    => $expiresAt->format('Y-m-d H:i:s'),
        ];

        $st = $this->sqlHelper->request($sql, $params);

        return $st->rowCount() >= 1;
    }

    /**
     * Finds token + user context by hash and purpose.
     *
     * @param string $hashBinary32
     * @param string $purpose
     *
     * @return array<string, mixed>|null
     */
    private function findContextByHashAndPurpose(string $hashBinary32, string $purpose): ?array
    {
        $sql = "
            SELECT
                u.id         AS user_id,
                u.status     AS user_status,
                t.used       AS used,
                t.used_at    AS used_at,
                t.expires_at AS expires_at,
                (t.expires_at < NOW()) AS is_expired
            FROM {$this->table} t
            JOIN user u ON u.id = t.user_id
            WHERE t.token_hash = :hash
              AND t.purpose = :purpose
            LIMIT 1
        ";

        $st = $this->sqlHelper->request($sql, [
            ':hash'    => $hashBinary32,
            ':purpose' => $purpose,
        ]);

        /** @var array<string,mixed>|false $row */
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function activateByHash(string $hashBinary32): bool
    {
        $sql = "
            UPDATE user u
            JOIN {$this->table} t ON t.user_id = u.id
            SET
                u.status = 'active',
                t.used    = 1,
                t.used_at = NOW()
            WHERE
                t.token_hash = :hash
                AND t.purpose = 'confirmation'
                AND t.used = 0
                AND t.expires_at > NOW()
                AND u.status <> 'active'
        ";

        $st = $this->sqlHelper->request($sql, [
            ':hash' => $hashBinary32,
        ]);

        return $st->rowCount() >= 1;
    }

    /**
     * Returns true if the user already has an active (unused + not expired) token
     * for the given purpose.
     */
    public function hasActiveUnusedToken(int $userId, string $purpose): bool
    {
        $sql = "
            SELECT 1
            FROM {$this->table}
            WHERE user_id = :user_id
              AND purpose = :purpose
              AND used = 0
              AND expires_at > NOW()
            LIMIT 1
        ";

        $st = $this->sqlHelper->request($sql, [
            ':user_id' => $userId,
            ':purpose' => $purpose,
        ]);

        return (bool) $st->fetchColumn();
    }

    public function invalidatePasswordResetToken(int $userId): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET
                used    = 1,
                used_at = NOW()
            WHERE
                user_id = :user_id
                AND purpose = 'password_reset'
                AND used = 0
                AND expires_at > NOW()
        ";

        $st = $this->sqlHelper->request($sql, [
            ':user_id' => $userId,
        ]);

        return $st->rowCount() >= 1;
    }

    public function createConfirmationToken(int $userId, string $hashBinary32, \DateTimeImmutable $expiresAt): bool
    {
        return $this->createToken($userId, 'confirmation', $hashBinary32, $expiresAt);
    }

    public function createPasswordResetToken(int $userId, string $hashBinary32, \DateTimeImmutable $expiresAt): bool
    {
        return $this->createToken($userId, 'password_reset', $hashBinary32, $expiresAt);
    }

    public function hasActiveUnusedPasswordResetToken(int $userId): bool
    {
        return $this->hasActiveUnusedToken($userId, 'password_reset');
    }

    public function findConfirmationContextByHash(string $hashBinary32): ?array
    {
        return $this->findContextByHashAndPurpose($hashBinary32, 'confirmation');
    }

    public function findPasswordResetContextByHash(string $hashBinary32): ?array
    {
        return $this->findContextByHashAndPurpose($hashBinary32, 'password_reset');
    }

    public function consumePasswordResetTokenAndUpdatePassword(string $hashBinary32, string $passwordHash): bool
    {
        // Une seule requête : met à jour user + token (comme votre activateByHash)
        $sql = "
            UPDATE user u
            JOIN {$this->table} t ON t.user_id = u.id
            SET
                u.password = :password_hash,
                t.used     = 1,
                t.used_at  = NOW()
            WHERE
                t.token_hash  = :hash
                AND t.purpose = 'password_reset'
                AND t.used    = 0
                AND t.expires_at > NOW()
        ";

        $st = $this->sqlHelper->request($sql, [
            ':password_hash' => $passwordHash,
            ':hash'          => $hashBinary32,
        ]);

        return $st->rowCount() >= 1;
    }
}
