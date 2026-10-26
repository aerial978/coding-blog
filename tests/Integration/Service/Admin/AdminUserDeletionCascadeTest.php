<?php

declare(strict_types=1);

namespace Tests\Integration\Service\Admin;

use App\Core\Database;
use PDO;
use RuntimeException;
use Tests\BaseTestCase;

final class AdminUserDeletionCascadeTest extends BaseTestCase
{
    private PDO $pdo;

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = (new Database())->getConnection();

        if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
            throw new RuntimeException(
                'Cascade tests must use the dedicated test database.'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->pdo)) {
                if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
                    throw new RuntimeException(
                        'Refusing to clean up outside the test database.'
                    );
                }

                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $statement = $this->pdo->prepare(
                    'DELETE FROM user WHERE id = :user_id'
                );

                foreach ($this->createdUserIds as $userId) {
                    $statement->execute(['user_id' => $userId]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    private function createTestUser(string $username): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user (role_id, username, slug, email, password, status)
            SELECT id, :username, :slug, :email, :password, :status
            FROM role
            WHERE name = :role'
        );

        $statement->execute([
            'role'     => 'MEMBER',
            'username' => $username,
            'slug'     => $username,
            'email'    => $username . '@example.test',
            'password' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'status'   => 'active',
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to create the test user.'
            );
        }

        $userId = (int) $this->pdo->lastInsertId();

        $this->createdUserIds[] = $userId;

        return $userId;
    }

    private function createTest2faChallenge(int $userId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_2fa_challenge (user_id, code_hash, expires_at)
            VALUES (:user_id, :code_hash, :expires_at)'
        );

        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':code_hash', random_bytes(32), PDO::PARAM_LOB);
        $statement->bindValue(
            ':expires_at',
            date('Y-m-d H:i:s', time() + 600)
        );

        $statement->execute();

        return (int) $this->pdo->lastInsertId();
    }

    private function createTestOAuthAccount(int $userId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_oauth_account
                (user_id, provider, provider_user_id, email)
            VALUES
                (:user_id, :provider, :provider_user_id, :email)'
        );

        $statement->execute([
            'user_id'          => $userId,
            'provider'         => 'google',
            'provider_user_id' => 'cascade_test_' . bin2hex(random_bytes(8)),
            'email'            => 'cascade_oauth_' . $userId . '@example.test',
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to create the test OAuth account.'
            );
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function testDeletingUserCascadesTo2faChallengesAndOAuthAccounts(): void
    {
        $username = 'cascade_delete_' . bin2hex(random_bytes(4));

        $userId = $this->createTestUser($username);

        $challengeId    = $this->createTest2faChallenge($userId);
        $oauthAccountId = $this->createTestOAuthAccount($userId);

        $challengeExists = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_2fa_challenge WHERE id = :id'
        );

        $oauthExists = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_oauth_account WHERE id = :id'
        );

        $challengeExists->execute(['id' => $challengeId]);
        self::assertSame(1, (int) $challengeExists->fetchColumn());

        $oauthExists->execute(['id' => $oauthAccountId]);
        self::assertSame(1, (int) $oauthExists->fetchColumn());

        $delete = $this->pdo->prepare(
            'DELETE FROM user WHERE id = :user_id'
        );

        $delete->execute(['user_id' => $userId]);

        self::assertSame(1, $delete->rowCount());

        $challengeExists->execute(['id' => $challengeId]);
        self::assertSame(
            0,
            (int) $challengeExists->fetchColumn(),
            'The 2FA challenge must be deleted automatically.'
        );

        $oauthExists->execute(['id' => $oauthAccountId]);
        self::assertSame(
            0,
            (int) $oauthExists->fetchColumn(),
            'The OAuth account must be deleted automatically.'
        );
    }
}
