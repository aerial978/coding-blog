<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Entity\OAuthAccountEntity;
use App\Model\OAuthAccountModel;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OAuthAccountModelTest extends TestCase
{
    private SqlHelperInterface&MockObject $sqlHelper;

    private PDOStatement&MockObject $statement;

    private OAuthAccountModel $model;

    protected function setUp(): void
    {
        $this->sqlHelper = $this->createMock(SqlHelperInterface::class);
        $this->statement = $this->createMock(PDOStatement::class);
        $this->model     = new OAuthAccountModel($this->sqlHelper);
    }

    public function testFindByProviderAndProviderUserIdReturnsOAuthAccount(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE provider = :provider'),
                [
                    ':provider'         => 'google',
                    ':provider_user_id' => 'google_123',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($this->oauthRow());

        $account = $this->model->findByProviderAndProviderUserId(
            'google',
            'google_123'
        );

        $this->assertInstanceOf(OAuthAccountEntity::class, $account);
        $this->assertSame(1, $account->getId());
        $this->assertSame(42, $account->getUserId());
        $this->assertSame('google', $account->getProvider());
        $this->assertSame('google_123', $account->getProviderUserId());
        $this->assertSame('michel@example.com', $account->getEmail());
        $this->assertTrue($account->isEmailVerified());
    }

    public function testFindByProviderAndProviderUserIdReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(false);

        $account = $this->model->findByProviderAndProviderUserId(
            'google',
            'unknown'
        );

        $this->assertNull($account);
    }

    public function testFindByProviderAndUserIdReturnsOAuthAccount(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('AND user_id = :user_id'),
                [
                    ':provider' => 'google',
                    ':user_id'  => 42,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($this->oauthRow());

        $account = $this->model->findByProviderAndUserId('google', 42);

        $this->assertInstanceOf(OAuthAccountEntity::class, $account);
        $this->assertSame(1, $account->getId());
        $this->assertSame(42, $account->getUserId());
        $this->assertSame('google', $account->getProvider());
        $this->assertSame('google_123', $account->getProviderUserId());
        $this->assertSame('michel@example.com', $account->getEmail());
        $this->assertTrue($account->isEmailVerified());
    }

    public function testFindByProviderAndUserIdReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(false);

        $account = $this->model->findByProviderAndUserId('google', 42);

        $this->assertNull($account);
    }

    public function testCreateReturnsInsertedIdWhenInsertSucceeds(): void
    {
        $account = (new OAuthAccountEntity())
            ->setUserId(42)
            ->setProvider('google')
            ->setProviderUserId('google_123')
            ->setEmail('michel@example.com')
            ->setEmailVerified(true);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user_oauth_account'),
                [
                    ':user_id'          => 42,
                    ':provider'         => 'google',
                    ':provider_user_id' => 'google_123',
                    ':email'            => 'michel@example.com',
                    ':email_verified'   => 1,
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
            ->willReturn(99);

        $createdId = $this->model->create($account);

        $this->assertSame(99, $createdId);
    }

    public function testCreateReturnsZeroWhenInsertFails(): void
    {
        $account = (new OAuthAccountEntity())
            ->setUserId(42)
            ->setProvider('google')
            ->setProviderUserId('google_123')
            ->setEmail('michel@example.com')
            ->setEmailVerified(false);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user_oauth_account'),
                [
                    ':user_id'          => 42,
                    ':provider'         => 'google',
                    ':provider_user_id' => 'google_123',
                    ':email'            => 'michel@example.com',
                    ':email_verified'   => 0,
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

        $createdId = $this->model->create($account);

        $this->assertSame(0, $createdId);
    }

    /**
     * @return array<string,mixed>
     */
    private function oauthRow(): array
    {
        return [
            'id'               => 1,
            'user_id'          => 42,
            'provider'         => 'google',
            'provider_user_id' => 'google_123',
            'email'            => 'michel@example.com',
            'email_verified'   => 1,
            'created_at'       => '2026-06-20 10:00:00',
            'updated_at'       => '2026-06-20 10:00:00',
        ];
    }
}
