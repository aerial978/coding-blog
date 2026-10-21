<?php

declare(strict_types=1);

namespace Tests\Integration\Service\Admin;

use App\Core\Database;
use App\Core\ErrorCode;
use App\Core\SqlHelper;
use App\Model\Contract\UserModelInterface;
use App\Model\EmailEventModel;
use App\Model\RegistrationEventModel;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Admin\AdminUserDeletionService;
use PDO;
use Tests\BaseTestCase;

final class AdminUserDeletionRecoveryTest extends BaseTestCase
{
    private PDO $pdo;
    /** @var list<int> */
    private array $createdUserIds = [];
    /** @var list<int> */
    private array $createdEmailEventIds = [];
    /** @var list<int> */
    private array $createdRegistrationEventIds = [];
    /** @var list<int> */
    private array $createdTokenIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = (new Database())->getConnection();

        $databaseName = $this->pdo->query('SELECT DATABASE()')->fetchColumn();

        self::assertSame(
            'coding-blog_test',
            $databaseName,
            'Integration tests must use the dedicated test database.'
        );
    }

    public function testConnectionUsesTestDatabase(): void
    {
        self::assertSame(
            'coding-blog_test',
            $this->pdo->query('SELECT DATABASE()')->fetchColumn()
        );
    }

    public function testAdminCanDeleteActiveMember(): void
    {
        $adminId  = $this->createTestUser('ADMIN', 'deletion_test_admin');
        $memberId = $this->createTestUser('MEMBER', 'deletion_test_member');

        $service = $this->createDeletionService($adminId);

        $result = $service->delete($memberId);

        self::assertSame([], $result);

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user WHERE id = :user_id'
        );

        $statement->execute(['user_id' => $memberId]);

        self::assertSame(0, (int) $statement->fetchColumn());

        $statement->execute(['user_id' => $adminId]);

        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testAdminCannotDeleteOwnAccount(): void
    {
        $adminId = $this->createTestUser('ADMIN', 'self_delete_test_admin');

        $service = $this->createDeletionService($adminId);

        $result = $service->delete($adminId);

        self::assertSame(
            ['errors' => [ErrorCode::ADMIN_USER_DELETE_SELF]],
            $result
        );

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user WHERE id = :user_id'
        );

        $statement->execute(['user_id' => $adminId]);

        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testAdminCanDeleteAnotherActiveAdmin(): void
    {
        $adminId  = $this->createTestUser('ADMIN', 'last_admin_test_actor');
        $targetId = $this->createTestUser('ADMIN', 'last_admin_test_target');

        $service = $this->createDeletionService($adminId);

        $result = $service->delete($targetId);

        self::assertSame([], $result);

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user WHERE id = :user_id'
        );

        $statement->execute(['user_id' => $targetId]);
        self::assertSame(0, (int) $statement->fetchColumn());

        $statement->execute(['user_id' => $adminId]);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testDeletingUserPreservesHistoricalEvents(): void
    {
        $adminId = $this->createTestUser(
            'ADMIN',
            'historical_events_test_admin'
        );

        $memberId = $this->createTestUser(
            'MEMBER',
            'historical_events_test_member'
        );

        $emailEventId = $this->createEmailEvent($memberId);
        $registrationEventId = $this->createRegistrationEvent($memberId);

        $service = $this->createDeletionService($adminId);

        $result = $service->delete($memberId);

        self::assertSame([], $result);

        // Verify that the email event still exists.
        $statement = $this->pdo->prepare(
            'SELECT user_id FROM email_events WHERE id = :event_id'
        );

        $statement->execute(['event_id' => $emailEventId]);

        $emailEvent = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray(
            $emailEvent,
            'The email event must be preserved.'
        );

        self::assertNull(
            $emailEvent['user_id'],
            'The email event must be detached from the deleted user.'
        );

        // Verify that the registration event still exists.
        $statement = $this->pdo->prepare(
            'SELECT user_id FROM registration_events WHERE id = :event_id'
        );

        $statement->execute(['event_id' => $registrationEventId]);

        $registrationEvent = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray(
            $registrationEvent,
            'The registration event must be preserved.'
        );

        self::assertNull(
            $registrationEvent['user_id'],
            'The registration event must be detached from the deleted user.'
        );
    }

    public function testDeletingUserRemovesOnlyTheirTokens(): void
    {
        $adminId = $this->createTestUser(
            'ADMIN',
            'token_deletion_test_admin'
        );

        $memberId = $this->createTestUser(
            'MEMBER',
            'token_deletion_test_member'
        );

        $targetTokenId = $this->createTestToken($memberId);
        $adminTokenId = $this->createTestToken($adminId);

        $service = $this->createDeletionService($adminId);

        $result = $service->delete($memberId);

        self::assertSame([], $result);

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_token WHERE id = :token_id'
        );

        // The deleted user's token must be removed.
        $statement->execute(['token_id' => $targetTokenId]);

        self::assertSame(
            0,
            (int) $statement->fetchColumn(),
            'The deleted user token must be removed.'
        );

        // The administrator's token must be preserved.
        $statement->execute(['token_id' => $adminTokenId]);

        self::assertSame(
            1,
            (int) $statement->fetchColumn(),
            'Other users tokens must be preserved.'
        );
    }

    public function testDeletionFailureRollsBackAllDatabaseChanges(): void
    {
        $adminId = $this->createTestUser(
            'ADMIN',
            'rollback_test_admin'
        );

        $memberId = $this->createTestUser(
            'MEMBER',
            'rollback_test_member'
        );

        $emailEventId = $this->createEmailEvent($memberId);
        $registrationEventId = $this->createRegistrationEvent($memberId);
        $tokenId = $this->createTestToken($memberId);

        $service = $this->createDeletionService(
            $adminId,
            $this->createFailingUserModel()
        );

        $result = $service->delete($memberId);

        self::assertSame(
            ['errors' => [ErrorCode::ADMIN_USER_DELETE_FAILED]],
            $result
        );

        self::assertFalse(
            $this->pdo->inTransaction(),
            'The transaction must be closed after rollback.'
        );

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user WHERE id = :id'
        );
        $statement->execute(['id' => $memberId]);

        self::assertSame(
            1,
            (int) $statement->fetchColumn(),
            'The user must be preserved.'
        );

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM user_token WHERE id = :id'
        );
        $statement->execute(['id' => $tokenId]);

        self::assertSame(
            1,
            (int) $statement->fetchColumn(),
            'The token must be restored.'
        );

        foreach (
            [
            'email_events' => $emailEventId,
            'registration_events' => $registrationEventId,
            ] as $table => $eventId
        ) {
            $statement = $this->pdo->prepare(
                "SELECT user_id FROM {$table} WHERE id = :id"
            );
            $statement->execute(['id' => $eventId]);

            self::assertSame(
                $memberId,
                (int) $statement->fetchColumn(),
                "The {$table} event must remain linked to the user."
            );
        }
    }

    /**
     * Creates a fictitious user in the test database.
     */
    private function createTestUser(string $role, string $username): int
    {
        if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
            throw new \RuntimeException(
                'Refusing to create test users outside the test database.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO user (role_id, username, slug, email, password, status)
            SELECT id, :username, :slug, :email, :password, :status
            FROM role
            WHERE name = :role'
        );

        $statement->execute([
            'role'     => $role,
            'username' => $username,
            'slug'     => $username,
            'email'    => $username . '@example.test',
            'password' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'status'   => 'active',
        ]);

        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Unable to create the test user.');
        }

        $userId = (int) $this->pdo->lastInsertId();

        $this->createdUserIds[] = $userId;

        return $userId;
    }

    private function createEmailEvent(int $userId): int
    {
        if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
            throw new \RuntimeException(
                'Refusing to create test events outside the test database.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO email_events (user_id, email, type, created_at)
            VALUES (:user_id, :email, :type, NOW())'
        );

        $statement->execute([
            'user_id' => $userId,
            'email'   => 'deletion_event_test@example.test',
            'type'    => 'test',
        ]);

        $eventId = (int) $this->pdo->lastInsertId();

        $this->createdEmailEventIds[] = $eventId;

        return $eventId;
    }

    private function createRegistrationEvent(int $userId): int
    {
        if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
            throw new \RuntimeException(
                'Refusing to create test events outside the test database.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO registration_events
                (user_id, email, type, ip, user_agent)
            VALUES
                (:user_id, :email, :type, :ip, :user_agent)'
        );

        $statement->execute([
            'user_id'    => $userId,
            'email'      => 'registration_deletion_test@example.test',
            'type'       => 'test',
            'ip'         => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $eventId = (int) $this->pdo->lastInsertId();

        $this->createdRegistrationEventIds[] = $eventId;

        return $eventId;
    }

    private function createTestToken(int $userId): int
    {
        if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'coding-blog_test') {
            throw new \RuntimeException(
                'Refusing to create test tokens outside the test database.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO user_token (user_id, purpose, token_hash, expires_at)
            VALUES (:user_id, :purpose, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        );

        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':purpose', 'password_reset');
        $statement->bindValue(':token_hash', random_bytes(32), PDO::PARAM_LOB);

        $statement->execute();

        $tokenId = (int) $this->pdo->lastInsertId();

        $this->createdTokenIds[] = $tokenId;

        return $tokenId;
    }

    /**
     * Creates an authentication checker for an active administrator.
     */
    private function createAdminAuthChecker(int $adminId): AuthCheckerInterface
    {
        $authChecker = $this->createMock(AuthCheckerInterface::class);

        $authChecker->method('isAuthenticated')->willReturn(true);
        $authChecker->method('getUserId')->willReturn($adminId);
        $authChecker->method('getRoles')->willReturn(['ADMIN']);

        return $authChecker;
    }

    private function createDeletionService(int $adminId, ?UserModelInterface $userModel = null): AdminUserDeletionService
    {
        $sqlHelper = new SqlHelper($this->pdo);

        return new AdminUserDeletionService(
            $userModel ?? new UserModel($sqlHelper),
            new UserTokenModel($sqlHelper),
            new EmailEventModel($sqlHelper),
            new RegistrationEventModel($sqlHelper),
            $sqlHelper,
            $this->createAdminAuthChecker($adminId)
        );
    }

    private function createFailingUserModel(): UserModelInterface
    {
        $realUserModel = new UserModel(new SqlHelper($this->pdo));

        $userModel = $this->createMock(UserModelInterface::class);

        $userModel->method('lockAdminRole')
            ->willReturnCallback(
                static fn () => $realUserModel->lockAdminRole()
            );

        $userModel->method('findOneByIdForUpdate')
            ->willReturnCallback(
                static fn (int $userId) => $realUserModel->findOneByIdForUpdate($userId)
            );

        $userModel->method('deleteById')
            ->willReturn(false);

        return $userModel;
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->pdo)) {
                $databaseName = $this->pdo->query('SELECT DATABASE()')->fetchColumn();

                if ($databaseName !== 'coding-blog_test') {
                    throw new \RuntimeException(
                        'Refusing to clean up outside the test database.'
                    );
                }

                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $statement = $this->pdo->prepare(
                    'DELETE FROM user_token WHERE id = :token_id'
                );

                foreach ($this->createdTokenIds as $tokenId) {
                    $statement->execute(['token_id' => $tokenId]);
                }

                foreach (
                    [
                    'email_events' => $this->createdEmailEventIds,
                    'registration_events' => $this->createdRegistrationEventIds,
                    ] as $table => $eventIds
                ) {
                    $statement = $this->pdo->prepare(
                        "DELETE FROM {$table} WHERE id = :event_id"
                    );

                    foreach ($eventIds as $eventId) {
                        $statement->execute(['event_id' => $eventId]);
                    }
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
}
