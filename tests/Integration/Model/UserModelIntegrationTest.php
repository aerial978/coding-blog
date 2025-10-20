<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use App\Core\Database;
use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use PHPUnit\Framework\TestCase;

final class UserModelIntegrationTest extends TestCase
{
    private string $dummyUsername;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dummyUsername = 'test_user_' . uniqid();

        $pdo  = (new Database())->getConnection();
        $stmt = $pdo->prepare('INSERT INTO user (username, email) VALUES (:username, :email)');
        $stmt->execute([
            'username' => $this->dummyUsername,
            'email'    => $this->dummyUsername . '@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        $pdo  = (new Database())->getConnection();
        $stmt = $pdo->prepare('DELETE FROM user WHERE username = :username');
        $stmt->execute(['username' => $this->dummyUsername]);
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
}
