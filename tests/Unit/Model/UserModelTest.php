<?php

namespace Tests\Unit\Model;

use App\Core\Database;
use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the UserModel class.
 *
 * This test suite ensures that UserModel correctly retrieves
 * user records from the database and returns them as UserEntity instances.
 */
final class UserModelTest extends TestCase
{
    /**
     * A randomly generated username used for creating and testing a dummy user.
     *
     * @var string
     */
    private string $dummyUsername;

    /**
     * Set up the test environment.
     *
     * Creates a dummy user in the database before each test to ensure
     * that the UserModel has data to retrieve.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dummyUsername = 'test_user_' . uniqid();

        $pdo  = (new Database())->getConnection();
        $stmt = $pdo->prepare('INSERT INTO user (username, email) VALUES (:username, :email)');
        $stmt->execute([
            'username' => $this->dummyUsername,
            'email'    => $this->dummyUsername . '@example.com'
        ]);
    }

    /**
     * Tear down the test environment.
     *
     * Removes the dummy user from the database after each test
     * to prevent leftover test data.
     */
    protected function tearDown(): void
    {
        $pdo  = (new Database())->getConnection();
        $stmt = $pdo->prepare('DELETE FROM user WHERE username = :username');
        $stmt->execute(['username' => $this->dummyUsername]);
    }

    /**
     * Test that findAll() returns an array of UserEntity objects.
     *
     * This method:
     * - Creates a UserModel instance with a real SqlHelper and a mocked logger.
     * - Calls findAll() to retrieve users from the database.
     * - Asserts that the result is not empty and contains UserEntity instances.
     */
    public function testFindAllReturnsUserArray(): void
    {
        $pdo       = (new Database())->getConnection();
        $sqlHelper = new SqlHelper($pdo);

        $logger = $this->createMock(LoggerInterface::class);

        $model = new UserModel($sqlHelper, $logger);

        $users = $model->findAll();

        $this->assertNotEmpty($users);
        $this->assertInstanceOf(UserEntity::class, $users[0]);
    }
}
