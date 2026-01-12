<?php

namespace App\Model;

use App\Core\SqlHelper;
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
     * @param SqlHelper $sqlHelper
     *     Helper utility for preparing and executing SQL queries.
     */
    public function __construct(
        private SqlHelper $sqlHelper
    ) {
    }

    /**
     * Creates or updates a confirmation token for a given user.
     *
     * If a token already exists for the specified user, it will be replaced.
     * The method stores the SHA-256 binary hash, expiration timestamp,
     * and resets usage information.
     *
     * @param int $userId
     *     The unique identifier of the user.
     * @param string $hashBinary32
     *     The binary SHA-256 hash of the token (must be exactly 32 bytes).
     * @param \DateTimeInterface $expiresAt
     *     The expiration timestamp of the token.
     *
     * @return bool
     *     True if the token was successfully inserted or updated, false otherwise.
     *
     * @throws \InvalidArgumentException
     *     If the token hash does not contain exactly 32 bytes.
     */
    public function createConfirmationToken(int $userId, string $hashBinary32, \DateTimeInterface $expiresAt): bool
    {
        // Sécurité : s'assurer qu'on a bien 32 octets
        if (strlen($hashBinary32) !== 32) {
            throw new InvalidArgumentException('Token hash must be exactly 32 bytes (SHA-256).');
        }

        $sql = "INSERT INTO {$this->table} 
                    (user_id, type, token_hash, expires_at, used, created_at) 
                VALUES 
                    (:user_id, 'confirmation', :token_hash, :expires_at, 0, NOW())
                ON DUPLICATE KEY UPDATE
                    token_hash = VALUES(token_hash),
                    expires_at = VALUES(expires_at),
                    used       = 0,
                    used_at    = NULL,
                    created_at = VALUES(created_at)";

        $params = [
            ':user_id'    => $userId,
            ':token_hash' => $hashBinary32,
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ];

        $st = $this->sqlHelper->request($sql, $params);

        return $st->rowCount() >= 1;
    }

    /**
     * Retrieves the confirmation context associated with a token hash.
     *
     * This method joins the `user_token` and `user` tables to provide
     * contextual information (status, usage, expiration) for a given token.
     *
     * @param string $hashBinary32
     *     The binary SHA-256 hash of the token.
     *
     * @return array<string, mixed>|null
     *     An associative array containing token and user context, or null if not found.
     */
    public function findConfirmationContextByHash(string $hashBinary32): ?array
    {
        $sql = "
            SELECT
            u.id              AS user_id,
            u.status          AS user_status,
            t.used            AS used,
            t.used_at         AS used_at,
            t.expires_at      AS expires_at,
            (t.expires_at < NOW()) AS is_expired
            FROM user_token t
            JOIN user u ON u.id = t.user_id
            WHERE t.token_hash = :hash AND t.type = 'confirmation'
            LIMIT 1
        ";
        $st = $this->sqlHelper->request($sql, [
            ':hash' => $hashBinary32,
        ]);

        /** @var array<string,mixed>|false $row */
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Activates a user account by confirmation token.
     *
     * Marks both the token and the user record as "used" and "active"
     * respectively, provided the token is valid, unused, and unexpired.
     *
     * @param string $hashBinary32
     *     The binary SHA-256 hash of the confirmation token.
     *
     * @return bool
     *     True if the user was successfully activated, false otherwise.
     */
    public function activateByHash(string $hashBinary32): bool
    {
        $sql = "
            UPDATE user u
            JOIN user_token t ON t.user_id = u.id
            SET
            u.status = 'active',
            t.used        = 1,
            t.used_at     = NOW()
            WHERE
            t.token_hash = :hash
            AND t.type = 'confirmation'
            AND t.used = 0
            AND t.expires_at > NOW()
            AND u.status <> 'active'
        ";

        $st = $this->sqlHelper->request($sql, [':hash' => $hashBinary32]);

        return $st->rowCount() >= 1;
    }
}
