<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Contract\OAuthAccountModelInterface;
use App\Model\Entity\OAuthAccountEntity;

/**
 * Handles database operations related to OAuth account links.
 *
 * This model acts as the data access layer for records stored in the
 * `user_oauth_account` table. It is responsible for finding existing
 * OAuth provider links and creating new links between external OAuth
 * accounts and local application users.
 */
class OAuthAccountModel implements OAuthAccountModelInterface
{
    protected string $table = 'user_oauth_account';

    /**
     * Constructor.
     *
     * Initializes the model with a SqlHelper instance for executing
     * database queries.
     */
    public function __construct(private SqlHelperInterface $sqlHelper)
    {
    }

    /**
     * Finds an OAuth account link by provider and provider user identifier.
     *
     * This method is used during OAuth login to determine whether the
     * external provider account is already linked to a local user.
     */
    public function findByProviderAndProviderUserId(
        string $provider,
        string $providerUserId
    ): ?OAuthAccountEntity {
        $sql = "SELECT
                    id,
                    user_id,
                    provider,
                    provider_user_id,
                    email,
                    email_verified,
                    created_at,
                    updated_at
                FROM {$this->table}
                WHERE provider = :provider
                    AND provider_user_id = :provider_user_id
                LIMIT 1";

        $stmt = $this->sqlHelper->request($sql, [
            ':provider'         => $provider,
            ':provider_user_id' => $providerUserId,
        ]);

        /** @var array<string,mixed>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row
            ? (new OAuthAccountEntity())->hydrate((array) $row)
            : null;
    }

    /**
     * Finds an OAuth account link by provider and local user identifier.
     *
     * This method is used before creating a new OAuth link to ensure
     * that the local user does not already have an account linked
     * with the same OAuth provider.
     */
    public function findByProviderAndUserId(
        string $provider,
        int $userId
    ): ?OAuthAccountEntity {
        $sql = "SELECT
                    id,
                    user_id,
                    provider,
                    provider_user_id,
                    email,
                    email_verified,
                    created_at,
                    updated_at
                FROM {$this->table}
                WHERE provider = :provider
                    AND user_id = :user_id
                LIMIT 1";

        $stmt = $this->sqlHelper->request($sql, [
            ':provider' => $provider,
            ':user_id'  => $userId,
        ]);

        /** @var array<string,mixed>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row
            ? (new OAuthAccountEntity())->hydrate((array) $row)
            : null;
    }

    /**
     * Creates a new OAuth account link.
     *
     * Inserts a new row into the `user_oauth_account` table based on the
     * provided OAuthAccountEntity and returns the generated identifier.
     */
    public function create(OAuthAccountEntity $account): int
    {
        $sql = "INSERT INTO {$this->table} (
                    user_id,
                    provider,
                    provider_user_id,
                    email,
                    email_verified,
                    created_at,
                    updated_at
                ) VALUES (
                    :user_id,
                    :provider,
                    :provider_user_id,
                    :email,
                    :email_verified,
                    NOW(),
                    NOW()
                )";

        $params = [
            ':user_id'          => $account->getUserId(),
            ':provider'         => $account->getProvider(),
            ':provider_user_id' => $account->getProviderUserId(),
            ':email'            => $account->getEmail(),
            ':email_verified'   => $account->isEmailVerified() ? 1 : 0,
        ];

        $query = $this->sqlHelper->request($sql, $params);

        if ($query->rowCount() !== 1) {
            return 0;
        }

        return $this->sqlHelper->lastInsertId();
    }
}
