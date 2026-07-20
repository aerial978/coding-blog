<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Entity\UserEntity;
use App\Model\UserModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserModelTest extends TestCase
{
    private SqlHelperInterface&MockObject $sqlHelper;
    private \PDOStatement&MockObject $statement;

    private UserModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlHelper = $this->createMock(SqlHelperInterface::class);
        $this->statement = $this->createMock(\PDOStatement::class);

        $this->model = new UserModel($this->sqlHelper);
    }

    public function testFindAllReturnsHydratedUsers(): void
    {
        $rows = [
            [
                'user_id'    => 1,
                'username'   => 'alice',
                'email'      => 'alice@example.com',
                'created_at' => '2026-01-01 10:00:00',
            ],
            [
                'user_id'    => 2,
                'username'   => 'bob',
                'email'      => 'bob@example.com',
                'created_at' => '2026-01-02 11:00:00',
            ],
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with('SELECT id AS user_id, username, email, created_at FROM user')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($rows);

        $result = $this->model->findAll();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(UserEntity::class, $result);

        $this->assertSame(1, $result[0]->getUserId());
        $this->assertSame('alice', $result[0]->getUsername());
        $this->assertSame('alice@example.com', $result[0]->getEmail());

        $this->assertSame(2, $result[1]->getUserId());
        $this->assertSame('bob', $result[1]->getUsername());
        $this->assertSame('bob@example.com', $result[1]->getEmail());
    }

    public function testFindAllReturnsEmptyArrayWhenNoUsersFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with('SELECT id AS user_id, username, email, created_at FROM user')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([]);

        $result = $this->model->findAll();

        $this->assertSame([], $result);
    }

    public function testFindOneByUsernameReturnsHydratedUserWhenFound(): void
    {
        $username = 'alice';

        $row = [
            'username' => 'alice',
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                'SELECT username FROM user WHERE username = :username',
                [':username' => $username]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $result = $this->model->findOneByUsername($username);

        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertSame('alice', $result->getUsername());
    }

    public function testFindOneByUsernameReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                'SELECT username FROM user WHERE username = :username',
                [':username' => 'unknown']
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->model->findOneByUsername('unknown');

        $this->assertNull($result);
    }

    public function testFindOneByEmailReturnsHydratedUserWhenFound(): void
    {
        $email = 'alice@example.com';

        $row = [
            'user_id'           => 12,
            'username'          => 'alice',
            'slug'              => 'alice',
            'email'             => 'alice@example.com',
            'password'          => 'hashed-password',
            'status'            => 'inactive',
            'created_at'        => '2026-01-01 10:00:00',
            'updated_at'        => '2026-01-01 10:00:00',
            'email_2fa_enabled' => 1,
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE email = :email'),
                [':email' => $email]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $result = $this->model->findOneByEmail($email);

        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertSame(12, $result->getUserId());
        $this->assertSame('alice', $result->getUsername());
        $this->assertSame('alice@example.com', $result->getEmail());
        $this->assertSame('hashed-password', $result->getPassword());
        $this->assertSame('inactive', $result->getStatus());
        $this->assertTrue($result->isEmail2faEnabled());
    }

    public function testFindOneByEmailReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE email = :email'),
                [':email' => 'unknown@example.com']
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->model->findOneByEmail('unknown@example.com');

        $this->assertNull($result);
    }

    public function testCreateUserReturnsInsertedIdWhenInsertSucceeds(): void
    {
        $user = (new UserEntity())
            ->setUsername('alice')
            ->setSlug('alice')
            ->setEmail('alice@example.com')
            ->setPassword('hashed-password');

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user'),
                [
                    ':username' => 'alice',
                    ':slug'     => 'alice',
                    ':email'    => 'alice@example.com',
                    ':password' => 'hashed-password',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->sqlHelper
            ->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(25);

        $result = $this->model->createUser($user);

        $this->assertSame(25, $result);
    }

    public function testCreateUserReturnsZeroWhenInsertFails(): void
    {
        $user = (new UserEntity())
            ->setUsername('alice')
            ->setSlug('alice')
            ->setEmail('alice@example.com')
            ->setPassword('hashed-password');

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $this->sqlHelper
            ->expects($this->never())
            ->method('lastInsertId');

        $result = $this->model->createUser($user);

        $this->assertSame(0, $result);
    }

    public function testFindAuthByEmailReturnsHydratedUserWhenFound(): void
    {
        $email = 'alice@example.com';

        $row = [
            'user_id'           => 7,
            'username'          => 'alice',
            'email'             => 'alice@example.com',
            'password'          => 'hashed-password',
            'status'            => 'active',
            'email_2fa_enabled' => 1,
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE email = :email'),
                [':email' => $email]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $result = $this->model->findAuthByEmail($email);

        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertSame(7, $result->getUserId());
        $this->assertSame('alice', $result->getUsername());
        $this->assertSame('alice@example.com', $result->getEmail());
        $this->assertSame('hashed-password', $result->getPassword());
        $this->assertSame('active', $result->getStatus());
        $this->assertTrue($result->isEmail2faEnabled());
    }

    public function testFindAuthByEmailReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE email = :email'),
                [':email' => 'unknown@example.com']
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->model->findAuthByEmail('unknown@example.com');

        $this->assertNull($result);
    }

    public function testFindAuthByUsernameReturnsHydratedUserWhenFound(): void
    {
        $username = 'alice';

        $row = [
            'user_id'           => 8,
            'username'          => 'alice',
            'email'             => 'alice@example.com',
            'password'          => 'hashed-password',
            'status'            => 'active',
            'email_2fa_enabled' => 1,
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE username = :username'),
                [':username' => $username]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $result = $this->model->findAuthByUsername($username);

        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertSame(8, $result->getUserId());
        $this->assertSame('alice', $result->getUsername());
        $this->assertSame('alice@example.com', $result->getEmail());
        $this->assertSame('hashed-password', $result->getPassword());
        $this->assertSame('active', $result->getStatus());
        $this->assertTrue($result->isEmail2faEnabled());
    }

    public function testFindAuthByUsernameReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE username = :username'),
                [':username' => 'unknown']
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->model->findAuthByUsername('unknown');

        $this->assertNull($result);
    }

    public function testFindOneByIdReturnsHydratedUserWhenFound(): void
    {
        $userId = 42;

        $row = [
            'user_id'           => 42,
            'username'          => 'alice',
            'slug'              => 'alice',
            'email'             => 'alice@example.com',
            'password'          => 'hashed-password',
            'status'            => 'active',
            'email_2fa_enabled' => 1,
            'created_at'        => '2026-01-01 10:00:00',
            'updated_at'        => '2026-01-01 10:00:00',
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE id = :user_id'),
                [':user_id' => $userId]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $result = $this->model->findOneById($userId);

        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertSame(42, $result->getUserId());
        $this->assertSame('alice', $result->getUsername());
        $this->assertSame('alice', $result->getSlug());
        $this->assertSame('alice@example.com', $result->getEmail());
        $this->assertSame('hashed-password', $result->getPassword());
        $this->assertSame('active', $result->getStatus());
        $this->assertTrue($result->isEmail2faEnabled());
    }

    public function testFindOneByIdReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE id = :user_id'),
                [':user_id' => 999]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->model->findOneById(999);

        $this->assertNull($result);
    }

    public function testFindOneBySlugReturnsHydratedUserWhenFound(): void
    {
        $slug = 'alice';

        $row = [
            'user_id'           => 42,
            'username'          => 'alice',
            'slug'              => 'alice',
            'email'             => 'alice@example.com',
            'password'          => 'hashed-password',
            'status'            => 'active',
            'email_2fa_enabled' => 1,
            'created_at'        => '2026-01-01 10:00:00',
            'updated_at'        => '2026-01-01 10:00:00',
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE slug = :slug'),
                [':slug' => $slug]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $result = $this->model->findOneBySlug($slug);

        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertSame(42, $result->getUserId());
        $this->assertSame('alice', $result->getUsername());
        $this->assertSame('alice', $result->getSlug());
        $this->assertSame('alice@example.com', $result->getEmail());
        $this->assertSame('hashed-password', $result->getPassword());
        $this->assertSame('active', $result->getStatus());
        $this->assertTrue($result->isEmail2faEnabled());
    }

    public function testFindOneBySlugReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE slug = :slug'),
                [':slug' => 'unknown']
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->model->findOneBySlug('unknown');

        $this->assertNull($result);
    }

    public function testCreateOAuthUserReturnsInsertedIdWhenInsertSucceeds(): void
    {
        $user = (new UserEntity())
            ->setUsername('oauth_user')
            ->setSlug('oauth-user')
            ->setEmail('oauth@example.com')
            ->setPassword('technical-hashed-password');

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('INSERT INTO user'),
                    $this->stringContains("'active'"),
                    $this->stringContains('email_2fa_enabled')
                ),
                [
                    ':username' => 'oauth_user',
                    ':slug'     => 'oauth-user',
                    ':email'    => 'oauth@example.com',
                    ':password' => 'technical-hashed-password',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->sqlHelper
            ->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(77);

        $result = $this->model->createOAuthUser($user);

        $this->assertSame(77, $result);
    }

    public function testCreateOAuthUserReturnsZeroWhenInsertFails(): void
    {
        $user = (new UserEntity())
            ->setUsername('oauth_user')
            ->setSlug('oauth-user')
            ->setEmail('oauth@example.com')
            ->setPassword('technical-hashed-password');

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user'),
                [
                    ':username' => 'oauth_user',
                    ':slug'     => 'oauth-user',
                    ':email'    => 'oauth@example.com',
                    ':password' => 'technical-hashed-password',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $this->sqlHelper
            ->expects($this->never())
            ->method('lastInsertId');

        $result = $this->model->createOAuthUser($user);

        $this->assertSame(0, $result);
    }
}
