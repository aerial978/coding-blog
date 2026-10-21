<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use App\Core\Database;
use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use Tests\BaseTestCase;

final class UserModelIntegrationTest extends BaseTestCase
{
    private string $dummyUsername;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dummyUsername = 'test_user_' . uniqid();

        $pdo = (new Database())->getConnection();

        $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();

        if ($databaseName !== 'coding-blog_test') {
            throw new \RuntimeException(
                'Integration tests must use the dedicated test database.'
            );
        }

        $stmt = $pdo->prepare(
            'SELECT id FROM role WHERE name = :name'
        );

        $stmt->execute(['name' => 'MEMBER']);

        $roleId = $stmt->fetchColumn();

        if ($roleId === false) {
            $this->fail('The MEMBER role does not exist in the test database.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO user (username, slug, email, password, role_id)
            VALUES (:username, :slug, :email, :password, :role_id)'
        );

        $stmt->execute([
            'username' => $this->dummyUsername,
            'slug'     => $this->dummyUsername,
            'email'    => $this->dummyUsername . '@example.com',
            'password' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'role_id'  => $roleId,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            $pdo = (new Database())->getConnection();

            $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();

            if ($databaseName !== 'coding-blog_test') {
                throw new \RuntimeException(
                    'Refusing to clean up outside the test database.'
                );
            }

            $stmt = $pdo->prepare(
                'DELETE FROM user WHERE username = :username'
            );

            $stmt->execute([
                'username' => $this->dummyUsername,
            ]);
        } finally {
            parent::tearDown();
        }
    }

    public function testFindAllReturnsUserArray(): void
    {
        $pdo       = (new Database())->getConnection();
        $sqlHelper = new SqlHelper($pdo);
        $model     = new UserModel($sqlHelper);

        $users = $model->findAll();

        $this->assertNotEmpty($users);
        $this->assertInstanceOf(UserEntity::class, $users[0]);
    }

    public function testConnectionUsesTestDatabase(): void
    {
        $pdo = (new Database())->getConnection();

        $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();

        $this->assertSame('coding-blog_test', $databaseName);
    }
}
