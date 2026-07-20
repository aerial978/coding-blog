<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;

/**
 * Handles database operations related to the User entity.
 *
 * This model acts as the data access layer (DAL) for user records.
 * It encapsulates SQL queries related to user persistence, retrieval,
 * and creation, using the SqlHelper abstraction for safe and reusable
 * database interactions.
 */
class UserModel implements UserModelInterface
{
    protected string $table = 'user';

    /**
     * Constructor.
     *
     * Initializes the model with a SqlHelper instance for
     * executing database queries.
     *
     * @param SqlHelperInterface $sqlHelper
     *     Helper class for preparing, executing, and managing SQL statements.
     */
    public function __construct(private SqlHelperInterface $sqlHelper)
    {
    }

    /**
     * Retrieves all users from the database.
     *
     * Executes a SELECT query on the user table and returns a list of hydrated
     * UserEntity objects representing each user.
     *
     * @return UserEntity[]
     *     An array of UserEntity objects for all users found.
     */
    public function findAll(): array
    {
        $stmt = $this->sqlHelper->request("SELECT id AS user_id, username, email, created_at FROM {$this->table}");

        /** @var list<array<string,mixed>> $results */
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $users = [];

        /** @var array<string, mixed> $row */
        foreach ($results as $row) {
            $users[] = (new UserEntity())->hydrate($row);
        }
        return $users;
    }

    /**
     * Finds a single user by their username.
     *
     * @param string $username
     *     The username to look up.
     *
     * @return UserEntity|null
     *     The corresponding UserEntity if found, or null otherwise.
     */
    public function findOneByUsername(string $username): ?UserEntity
    {
        $stmt = $this->sqlHelper->request(
            "SELECT username FROM {$this->table} WHERE username = :username",
            [':username' => $username]
        );

        /** @var array<string,mixed>|false $data */
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $data ? (new UserEntity())->hydrate((array) $data) : null;
    }

    /**
     * Finds a single user by its slug.
     *
     * Used notably to ensure slug uniqueness during OAuth
     * auto-provisioning and other account creation workflows.
     *
     * @param string $slug
     *     The slug to look up.
     *
     * @return UserEntity|null
     *     A hydrated UserEntity if found, null otherwise.
     */
    public function findOneBySlug(string $slug): ?UserEntity
    {
        $sql = "SELECT
                    id AS user_id,
                    username,
                    slug,
                    email,
                    password,
                    status,
                    email_2fa_enabled,
                    created_at,
                    updated_at
                FROM {$this->table}
                WHERE slug = :slug
                LIMIT 1";

        $stmt = $this->sqlHelper->request($sql, [
            ':slug' => $slug,
        ]);

        /** @var array<string,mixed>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row
            ? (new UserEntity())->hydrate((array) $row)
            : null;
    }

    /**
     * Finds a single user by their email address.
     *
     * @param string $email
     *     The email address to look up.
     *
     * @return UserEntity|null
     *     A hydrated UserEntity if found, or null if no record matches.
     */
    public function findOneByEmail(string $email): ?UserEntity
    {
        $sql = "SELECT 
                id AS user_id, username, slug, email, password,
                status,
                email_2fa_enabled,
                created_at, 
                updated_at
            FROM {$this->table}
            WHERE email = :email
            LIMIT 1";

        $stmt = $this->sqlHelper->request($sql, [':email' => $email]);

        /** @var array<string,mixed>|false $row */
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (new UserEntity())->hydrate((array) $row) : null;
    }

    /**
     * Finds a single user by its primary identifier.
     *
     * Used for authenticated flows requiring a full user entity,
     * especially email-based 2FA verification and resend flows.
     *
     * @param int $userId
     *     The user identifier.
     *
     * @return UserEntity|null
     *     A hydrated UserEntity if found, null otherwise.
     */
    public function findOneById(int $userId): ?UserEntity
    {
        $sql = "SELECT
                    id AS user_id,
                    username,
                    slug,
                    email,
                    password,
                    status,
                    email_2fa_enabled,
                    created_at,
                    updated_at
                FROM {$this->table}
                WHERE id = :user_id
                LIMIT 1";

        $stmt = $this->sqlHelper->request($sql, [
            ':user_id' => $userId,
        ]);

        /** @var array<string,mixed>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row
            ? (new UserEntity())->hydrate((array) $row)
            : null;
    }

    /**
     * Creates a new user record in the database.
     *
     * Inserts a new row into the `user` table based on the provided UserEntity.
     * The account is created with an initial status of "inactive".
     *
     * @param UserEntity $user
     *     The user entity containing data to insert.
     *
     * @return int
     *     The ID of the newly created user record, or 0 if the insertion failed.
     */
    public function createUser(UserEntity $user): int
    {
        $sql = "INSERT INTO {$this->table} (
                    username,
                    slug,
                    email,
                    password,
                    created_at,
                    updated_at,
                    status
                ) VALUES (
                    :username,
                    :slug,
                    :email,
                    :password,
                    NOW(),
                    NOW(),
                    'inactive'
                )";

        $params = [
            ':username' => $user->getUsername(),
            ':slug'     => $user->getSlug(),
            ':email'    => $user->getEmail(),
            ':password' => $user->getPassword(),
        ];

        $query = $this->sqlHelper->request($sql, $params);

        if ($query->rowCount() !== 1) {
            return 0;
        }

        return $this->sqlHelper->lastInsertId();
    }

    /**
     * Creates a new active user account from an OAuth provider.
     *
     * This method is dedicated to OAuth auto-provisioning.
     * Unlike classic registration, the account is created as active because
     * the OAuth provider has already verified the user's email address.
     *
     * The password must contain a technical hashed password generated
     * before calling this method.
     *
     * @param UserEntity $user
     *     The OAuth user entity containing username, slug, email and password.
     *
     * @return int
     *     The ID of the newly created user record, or 0 if the insertion failed.
     */
    public function createOAuthUser(UserEntity $user): int
    {
        $sql = "INSERT INTO {$this->table} (
                    username,
                    slug,
                    email,
                    password,
                    created_at,
                    updated_at,
                    status,
                    email_2fa_enabled
                ) VALUES (
                    :username,
                    :slug,
                    :email,
                    :password,
                    NOW(),
                    NOW(),
                    'active',
                    0
                )";

        $params = [
            ':username' => $user->getUsername(),
            ':slug'     => $user->getSlug(),
            ':email'    => $user->getEmail(),
            ':password' => $user->getPassword(),
        ];

        $query = $this->sqlHelper->request($sql, $params);

        if ($query->rowCount() !== 1) {
            return 0;
        }

        return $this->sqlHelper->lastInsertId();
    }

    /**
     * Retourne les champs nécessaires à l'authentification via email.
     */
    public function findAuthByEmail(string $email): ?UserEntity
    {
        $sql = "SELECT
                    id AS user_id,
                    username,
                    email,
                    password,
                    status,
                    email_2fa_enabled
                FROM {$this->table}
                WHERE email = :email
                LIMIT 1";

        $stmt = $this->sqlHelper->request($sql, [':email' => $email]);

        /** @var array<string,mixed>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (new UserEntity())->hydrate((array) $row) : null;
    }

    /**
     * Retourne les champs nécessaires à l'authentification via username.
     */
    public function findAuthByUsername(string $username): ?UserEntity
    {
        $sql = "SELECT
                    id AS user_id,
                    username,
                    email,
                    password,
                    status,
                    email_2fa_enabled
                FROM {$this->table}
                WHERE username = :username
                LIMIT 1";

        $stmt = $this->sqlHelper->request($sql, [':username' => $username]);

        /** @var array<string,mixed>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (new UserEntity())->hydrate((array) $row) : null;
    }
}
