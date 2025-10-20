<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Core\SqlHelper;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserModelTest extends TestCase
{
    /** @var SqlHelper&MockObject */
    private SqlHelper $sqlHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sqlHelper = $this->createMock(SqlHelper::class);
    }

    // =========================
    // findOneByUsername()
    // =========================

    public function testFindOneByUsernameReturnsEntityWhenFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);

        $this->sqlHelper
            ->method('request')
            ->with(
                $this->stringContains('SELECT username FROM user WHERE username = :username'),
                $this->equalTo([':username' => 'john'])
            )
            ->willReturn($stmt);

        $stmt->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(['username' => 'john']);

        $model  = new UserModel($this->sqlHelper);
        $entity = $model->findOneByUsername('john');

        $this->assertInstanceOf(UserEntity::class, $entity);
        $this->assertSame('john', $entity->getUsername());
    }

    public function testFindOneByUsernameReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);

        $this->sqlHelper
            ->method('request')
            ->with(
                $this->stringContains('SELECT username FROM user WHERE username = :username'),
                $this->equalTo([':username' => 'nobody'])
            )
            ->willReturn($stmt);

        $stmt->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $model = new UserModel($this->sqlHelper);
        $this->assertNull($model->findOneByUsername('nobody'));
    }

    // =========================
    // findOneByEmail()
    // =========================

    public function testFindOneByEmailReturnsEntityWhenFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);

        $this->sqlHelper
            ->method('request')
            ->with(
                $this->stringContains('SELECT'),
                [':email' => 'john@example.test']
            )
            ->willReturn($stmt);

        $stmt->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                'user_id'    => 42,
                'username'   => 'john',
                'slug'       => 'john',
                'email'      => 'john@example.test',
                'password'   => 'hashed',
                'status'     => 'inactive',
                'created_at' => '2025-10-03 12:00:00',
                'updated_at' => '2025-10-03 12:00:00',
            ]);

        $model  = new UserModel($this->sqlHelper);
        $entity = $model->findOneByEmail('john@example.test');

        $this->assertInstanceOf(UserEntity::class, $entity);
        $this->assertSame(42, $entity->getUserId());
        $this->assertSame('john@example.test', $entity->getEmail());
        $this->assertSame('inactive', $entity->getStatus());
    }

    public function testFindOneByEmailReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);

        $this->sqlHelper
            ->method('request')
            ->with(
                $this->stringContains('SELECT'),
                [':email' => 'nope@example.test']
            )
            ->willReturn($stmt);

        $stmt->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $model = new UserModel($this->sqlHelper);
        $this->assertNull($model->findOneByEmail('nope@example.test'));
    }

    // =========================
    // createUser()
    // =========================

    public function testCreateUserReturnsLastInsertIdOnSuccess(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user'),
                $this->callback(function (array $params) {
                    return $params[':username'] === 'John'
                        && $params[':slug']     === 'john'
                        && $params[':email']    === 'john@example.test'
                        && $params[':password'] === 'hashed';
                })
            )
            ->willReturn($stmt);

        $stmt->method('rowCount')->willReturn(1);

        $this->sqlHelper
            ->method('lastInsertId')
            ->willReturn(99);

        $entity = (new UserEntity())
            ->setUsername('John')
            ->setSlug('john')
            ->setEmail('john@example.test')
            ->setPassword('hashed');

        $model = new UserModel($this->sqlHelper);
        $this->assertSame(99, $model->createUser($entity));
    }

    public function testCreateUserReturnsZeroWhenNoRowInserted(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);

        $this->sqlHelper->method('request')->willReturn($stmt);
        $stmt->method('rowCount')->willReturn(0);

        $entity = (new UserEntity())
            ->setUsername('John')
            ->setSlug('john')
            ->setEmail('john@example.test')
            ->setPassword('hashed');

        $model = new UserModel($this->sqlHelper);
        $this->assertSame(0, $model->createUser($entity));
    }
}
