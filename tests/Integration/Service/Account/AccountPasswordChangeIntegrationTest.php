<?php

declare(strict_types=1);

namespace Tests\Integration\Service\Account;

use App\Core\Database;
use App\Core\ErrorCode;
use App\Core\SqlHelper;
use App\Model\Contract\UserTokenModelInterface;
use App\Model\UserModel;
use App\Model\UserTokenModel;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\PasswordBlacklist;
use App\Service\Account\AccountPasswordChangeService;
use App\Validation\FormValidator;
use PDO;
use RuntimeException;
use Tests\BaseTestCase;

final class AccountPasswordChangeIntegrationTest extends BaseTestCase
{
    private PDO $pdo;

    /** @var list<int> */
    private array $createdUserIds = [];

    /** @var list<int> */
    private array $createdTokenIds = [];

    public function testPasswordChangeRevokesOnlyActiveRememberMeTokens(): void
    {
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $userId      = $this->createTestUser($currentPassword);
        $otherUserId = $this->createTestUser($currentPassword);

        $activeRememberMeId    = $this->createTestToken($userId, 'remember_me');
        $passwordResetId       = $this->createTestToken($userId, 'password_reset');
        $otherUserRememberMeId = $this->createTestToken($otherUserId, 'remember_me');

        $authChecker = $this->createMock(AuthCheckerInterface::class);

        $authChecker
            ->method('getUserId')
            ->willReturn($userId);

        $sqlHelper = new SqlHelper($this->pdo);

        $service = new AccountPasswordChangeService(
            $authChecker,
            new UserModel($sqlHelper),
            new UserTokenModel($sqlHelper),
            new FormValidator(),
            new PasswordBlacklist([]),
            $sqlHelper
        );

        $result = $service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        self::assertSame(['ok' => true], $result);

        self::assertFalse(
            $this->pdo->inTransaction(),
            'The transaction must be closed after success.'
        );

        self::assertSame(
            ['used' => 1, 'used_at_set' => 1],
            $this->getTokenStatus($activeRememberMeId),
            'The active remember-me token must be revoked.'
        );

        self::assertSame(
            ['used' => 0, 'used_at_set' => 0],
            $this->getTokenStatus($passwordResetId),
            'The password-reset token must remain unchanged.'
        );

        self::assertSame(
            ['used' => 0, 'used_at_set' => 0],
            $this->getTokenStatus($otherUserRememberMeId),
            'Other users remember-me tokens must remain unchanged.'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = (new Database())->getConnection();

        self::assertSame(
            'coding-blog_test',
            $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
            'Integration tests must use the dedicated test database.'
        );
    }

    public function testSuccessfullyChangesPasswordWithoutRememberMeTokens(): void
    {
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $userId = $this->createTestUser($currentPassword);

        $authChecker = $this->createMock(AuthCheckerInterface::class);

        $authChecker
            ->method('getUserId')
            ->willReturn($userId);

        $sqlHelper = new SqlHelper($this->pdo);

        $service = new AccountPasswordChangeService(
            $authChecker,
            new UserModel($sqlHelper),
            new UserTokenModel($sqlHelper),
            new FormValidator(),
            new PasswordBlacklist([]),
            $sqlHelper
        );

        $result = $service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        self::assertSame(['ok' => true], $result);

        self::assertFalse(
            $this->pdo->inTransaction(),
            'The transaction must be closed after success.'
        );

        $statement = $this->pdo->prepare(
            'SELECT password FROM user WHERE id = :user_id'
        );

        $statement->execute(['user_id' => $userId]);

        $storedHash = $statement->fetchColumn();

        self::assertIsString($storedHash);

        self::assertTrue(
            password_verify($newPassword, $storedHash),
            'The new password must be accepted.'
        );

        self::assertFalse(
            password_verify($currentPassword, $storedHash),
            'The previous password must no longer be accepted.'
        );

        self::assertSame(
            PASSWORD_ARGON2ID,
            password_get_info($storedHash)['algo'],
            'The new password must use Argon2id.'
        );
    }

    public function testPasswordChangePreservesExpiredAndUsedRememberMeTokens(): void
    {
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $userId             = $this->createTestUser($currentPassword);
        $expiredTokenUserId = $this->createTestUser($currentPassword);
        $usedTokenUserId    = $this->createTestUser($currentPassword);

        $activeTokenId  = $this->createTestToken($userId, 'remember_me');
        $expiredTokenId = $this->createTestToken($expiredTokenUserId, 'remember_me');
        $usedTokenId    = $this->createTestToken($usedTokenUserId, 'remember_me');

        // Expire one token.
        $statement = $this->pdo->prepare(
            'UPDATE user_token
            SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
            WHERE id = :token_id'
        );

        $statement->execute(['token_id' => $expiredTokenId]);

        // Mark another token as already used.
        $statement = $this->pdo->prepare(
            'UPDATE user_token
            SET used = 1, used_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            WHERE id = :token_id'
        );

        $statement->execute(['token_id' => $usedTokenId]);

        $usedTokenStatusBefore = $this->getTokenStatus($usedTokenId);

        $authChecker = $this->createMock(AuthCheckerInterface::class);

        $authChecker
            ->method('getUserId')
            ->willReturn($userId);

        $sqlHelper = new SqlHelper($this->pdo);

        $service = new AccountPasswordChangeService(
            $authChecker,
            new UserModel($sqlHelper),
            new UserTokenModel($sqlHelper),
            new FormValidator(),
            new PasswordBlacklist([]),
            $sqlHelper
        );

        $result = $service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        self::assertSame(['ok' => true], $result);

        self::assertFalse(
            $this->pdo->inTransaction(),
            'The transaction must be closed after success.'
        );

        self::assertSame(
            ['used' => 1, 'used_at_set' => 1],
            $this->getTokenStatus($activeTokenId),
            'The active remember-me token must be revoked.'
        );

        self::assertSame(
            ['used' => 0, 'used_at_set' => 0],
            $this->getTokenStatus($expiredTokenId),
            'The expired remember-me token must remain unchanged.'
        );

        self::assertSame(
            $usedTokenStatusBefore,
            $this->getTokenStatus($usedTokenId),
            'The already-used remember-me token must remain unchanged.'
        );
    }

    public function testPasswordChangePreservesOwnExpiredRememberMeToken(): void
    {
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $userId  = $this->createTestUser($currentPassword);
        $tokenId = $this->createTestToken($userId, 'remember_me');

        $statement = $this->pdo->prepare(
            'UPDATE user_token
            SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
            WHERE id = :token_id'
        );

        $statement->execute(['token_id' => $tokenId]);

        self::assertSame(
            ['used' => 0, 'used_at_set' => 0],
            $this->getTokenStatus($tokenId)
        );

        $authChecker = $this->createMock(AuthCheckerInterface::class);
        $authChecker->method('getUserId')->willReturn($userId);

        $sqlHelper = new SqlHelper($this->pdo);

        $service = new AccountPasswordChangeService(
            $authChecker,
            new UserModel($sqlHelper),
            new UserTokenModel($sqlHelper),
            new FormValidator(),
            new PasswordBlacklist([]),
            $sqlHelper
        );

        $result = $service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        self::assertSame(['ok' => true], $result);

        self::assertFalse($this->pdo->inTransaction());

        self::assertSame(
            ['used' => 0, 'used_at_set' => 0],
            $this->getTokenStatus($tokenId),
            'The expired remember-me token must remain unchanged.'
        );
    }

    public function testPasswordChangePreservesOwnUsedRememberMeToken(): void
    {
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $userId  = $this->createTestUser($currentPassword);
        $tokenId = $this->createTestToken($userId, 'remember_me');

        // Mark the token as already used.
        $statement = $this->pdo->prepare(
            'UPDATE user_token
            SET used = 1,
                used_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            WHERE id = :token_id'
        );

        $statement->execute(['token_id' => $tokenId]);

        // Capture the exact state before changing the password.
        $statement = $this->pdo->prepare(
            'SELECT used, used_at
            FROM user_token
            WHERE id = :token_id'
        );

        $statement->execute(['token_id' => $tokenId]);

        $tokenBefore = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($tokenBefore);
        self::assertSame(1, (int) $tokenBefore['used']);
        self::assertNotNull($tokenBefore['used_at']);

        $authChecker = $this->createMock(AuthCheckerInterface::class);

        $authChecker
            ->method('getUserId')
            ->willReturn($userId);

        $sqlHelper = new SqlHelper($this->pdo);

        $service = new AccountPasswordChangeService(
            $authChecker,
            new UserModel($sqlHelper),
            new UserTokenModel($sqlHelper),
            new FormValidator(),
            new PasswordBlacklist([]),
            $sqlHelper
        );

        $result = $service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        self::assertSame(['ok' => true], $result);

        self::assertFalse(
            $this->pdo->inTransaction(),
            'The transaction must be closed after success.'
        );

        $statement->execute(['token_id' => $tokenId]);

        $tokenAfter = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertSame(
            $tokenBefore,
            $tokenAfter,
            'The already-used remember-me token must remain unchanged.'
        );
    }

    public function testTokenRevocationFailureRollsBackPasswordChange(): void
    {
        $currentPassword = 'Current123!Password';
        $newPassword     = 'New123!Password';

        $userId  = $this->createTestUser($currentPassword);
        $tokenId = $this->createTestToken($userId, 'remember_me');

        $statement = $this->pdo->prepare(
            'SELECT password FROM user WHERE id = :user_id'
        );

        $statement->execute(['user_id' => $userId]);
        $originalHash = $statement->fetchColumn();

        self::assertIsString($originalHash);

        $authChecker = $this->createMock(AuthCheckerInterface::class);
        $authChecker->method('getUserId')->willReturn($userId);

        $sqlHelper = new SqlHelper($this->pdo);

        $tokenModel = $this->createMock(UserTokenModelInterface::class);

        $tokenModel
            ->expects($this->once())
            ->method('revokeAllActiveRememberMeTokens')
            ->with($userId)
            ->willThrowException(
                new RuntimeException('Simulated token revocation failure.')
            );

        $service = new AccountPasswordChangeService(
            $authChecker,
            new UserModel($sqlHelper),
            $tokenModel,
            new FormValidator(),
            new PasswordBlacklist([]),
            $sqlHelper
        );

        $result = $service->changePassword([
            'current_password'      => $currentPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        self::assertSame(
            ['errors' => [
                'current_password' => ErrorCode::ACCOUNT_PASSWORD_UPDATE_FAILED,
            ]],
            $result
        );

        self::assertFalse(
            $this->pdo->inTransaction(),
            'The transaction must be closed after rollback.'
        );

        $statement->execute(['user_id' => $userId]);
        $storedHash = $statement->fetchColumn();

        self::assertSame(
            $originalHash,
            $storedHash,
            'The original password hash must be restored.'
        );

        self::assertTrue(password_verify($currentPassword, $storedHash));
        self::assertFalse(password_verify($newPassword, $storedHash));

        self::assertSame(
            ['used' => 0, 'used_at_set' => 0],
            $this->getTokenStatus($tokenId),
            'The remember-me token must remain unchanged.'
        );
    }

    public function testIncorrectCurrentPasswordDoesNotModifyDatabase(): void
    {
        $currentPassword   = 'Current123!Password';
        $incorrectPassword = 'Incorrect123!Password';
        $newPassword       = 'New123!Password';

        $userId  = $this->createTestUser($currentPassword);
        $tokenId = $this->createTestToken($userId, 'remember_me');

        $statement = $this->pdo->prepare(
            'SELECT password FROM user WHERE id = :user_id'
        );

        $statement->execute(['user_id' => $userId]);
        $originalHash = $statement->fetchColumn();

        self::assertIsString($originalHash);

        $authChecker = $this->createMock(AuthCheckerInterface::class);
        $authChecker->method('getUserId')->willReturn($userId);

        $sqlHelper = new SqlHelper($this->pdo);

        $service = new AccountPasswordChangeService(
            $authChecker,
            new UserModel($sqlHelper),
            new UserTokenModel($sqlHelper),
            new FormValidator(),
            new PasswordBlacklist([]),
            $sqlHelper
        );

        $result = $service->changePassword([
            'current_password'      => $incorrectPassword,
            'new_password'          => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        self::assertSame(
            ['errors' => [
                'current_password' => ErrorCode::ACCOUNT_CURRENT_PASSWORD_INVALID,
            ]],
            $result
        );

        self::assertFalse(
            $this->pdo->inTransaction(),
            'The transaction must be closed after rejection.'
        );

        $statement->execute(['user_id' => $userId]);
        $storedHash = $statement->fetchColumn();

        self::assertSame(
            $originalHash,
            $storedHash,
            'The password hash must remain unchanged.'
        );

        self::assertTrue(password_verify($currentPassword, $storedHash));
        self::assertFalse(password_verify($newPassword, $storedHash));

        self::assertSame(
            ['used' => 0, 'used_at_set' => 0],
            $this->getTokenStatus($tokenId),
            'The remember-me token must remain active.'
        );
    }

    private function createTestUser(string $password): int
    {
        $this->assertTestDatabase();

        $suffix   = bin2hex(random_bytes(6));
        $username = 'password_change_' . $suffix;

        $statement = $this->pdo->prepare(
            'INSERT INTO user
                (role_id, username, slug, email, password, status)
             SELECT id, :username, :slug, :email, :password, :status
             FROM role
             WHERE name = :role'
        );

        $statement->execute([
            'role'     => 'MEMBER',
            'username' => $username,
            'slug'     => $username,
            'email'    => $username . '@example.test',
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'status'   => 'active',
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Unable to create the test user.');
        }

        $userId = (int) $this->pdo->lastInsertId();

        $this->createdUserIds[] = $userId;

        return $userId;
    }

    private function assertTestDatabase(): void
    {
        if (
            $this->pdo->query('SELECT DATABASE()')->fetchColumn()
            !== 'coding-blog_test'
        ) {
            throw new RuntimeException(
                'Refusing database operations outside the test database.'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->pdo)) {
                $this->assertTestDatabase();

                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $statement = $this->pdo->prepare(
                    'DELETE FROM user_token WHERE id = :token_id'
                );

                foreach ($this->createdTokenIds as $tokenId) {
                    $statement->execute(['token_id' => $tokenId]);
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

    private function createTestToken(int $userId, string $purpose): int
    {
        $this->assertTestDatabase();

        $statement = $this->pdo->prepare(
            'INSERT INTO user_token
                (user_id, purpose, token_hash, expires_at)
            VALUES
                (:user_id, :purpose, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        );

        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':purpose', $purpose);
        $statement->bindValue(':token_hash', random_bytes(32), PDO::PARAM_LOB);

        $statement->execute();

        $tokenId = (int) $this->pdo->lastInsertId();

        $this->createdTokenIds[] = $tokenId;

        return $tokenId;
    }

    /**
 * @return array{used:int, used_at_set:int}
 */
    private function getTokenStatus(int $tokenId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT used, (used_at IS NOT NULL) AS used_at_set
         FROM user_token
         WHERE id = :token_id'
        );

        $statement->execute(['token_id' => $tokenId]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result)) {
            throw new RuntimeException('Test token not found.');
        }

        return [
            'used'        => (int) $result['used'],
            'used_at_set' => (int) $result['used_at_set'],
        ];
    }
}
