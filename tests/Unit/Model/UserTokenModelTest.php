<?php

namespace Tests\Unit\Model;

use App\Core\SqlHelper;
use App\Model\UserTokenModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserTokenModelTest extends TestCase
{
    /** @var SqlHelper&MockObject */
    private SqlHelper $sqlHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sqlHelper = $this->createMock(SqlHelper::class);
    }

    // =========================
    // createConfirmationToken()
    // =========================

    public function testCreateConfirmationTokenThrowsOnInvalidLength(): void
    {
        $model = new UserTokenModel($this->sqlHelper);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token hash must be exactly 32 bytes');

        // hash de longueur != 32
        $badHash = random_bytes(16);
        $model->createConfirmationToken(123, $badHash, new \DateTimeImmutable('+1 day'));
    }

    public function testCreateConfirmationTokenReturnsTrueOnUpsert(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);

        // on vérifie les params clefs
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user_token'),
                $this->callback(function (array $params): bool {
                    return isset($params[':user_id'], $params[':token_hash'], $params[':expires_at'])
                        && $params[':user_id'] === 42
                        && is_string($params[':token_hash'])
                        && strlen($params[':token_hash']) === 32
                        && is_string($params[':expires_at']);
                })
            )
            ->willReturn($stmt);

        // ON DUPLICATE → rowCount() typiquement 1 ou 2
        $stmt->method('rowCount')->willReturn(1);

        $hash32 = random_bytes(32);
        $model  = new UserTokenModel($this->sqlHelper);

        $this->assertTrue($model->createConfirmationToken(42, $hash32, new \DateTimeImmutable('+1 day')));
    }

    // ==================================
    // findConfirmationContextByHash()
    // ==================================

    public function testFindConfirmationContextByHashReturnsArrayWhenFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $hash = random_bytes(32);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with($this->stringContains('SELECT'), [':hash' => $hash])
            ->willReturn($stmt);

        $stmt->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([
                'user_id'      => 99,
                'user_status'  => 'inactive',
                'used'         => 0,
                'used_at'      => null,
                'expires_at'   => '2030-01-01 00:00:00',
                'is_expired'   => 0,
            ]);

        $model = new UserTokenModel($this->sqlHelper);
        $row   = $model->findConfirmationContextByHash($hash);

        $this->assertIsArray($row);
        $this->assertSame(99, $row['user_id']);
        $this->assertSame('inactive', $row['user_status']);
        $this->assertSame(0, $row['used']);
        $this->assertSame(0, $row['is_expired']);
    }

    public function testFindConfirmationContextByHashReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $hash = random_bytes(32);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with($this->stringContains('SELECT'), [':hash' => $hash])
            ->willReturn($stmt);

        $stmt->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $model = new UserTokenModel($this->sqlHelper);
        $this->assertNull($model->findConfirmationContextByHash($hash));
    }

    // =========================
    // activateByHash()
    // =========================

    public function testActivateByHashReturnsTrueWhenUpdated(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $hash = random_bytes(32);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with($this->stringContains('UPDATE user u'), [':hash' => $hash])
            ->willReturn($stmt);

        $stmt->method('rowCount')->willReturn(1);

        $model = new UserTokenModel($this->sqlHelper);
        $this->assertTrue($model->activateByHash($hash));
    }

    public function testActivateByHashReturnsFalseWhenNoRowUpdated(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $hash = random_bytes(32);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with($this->stringContains('UPDATE user u'), [':hash' => $hash])
            ->willReturn($stmt);

        $stmt->method('rowCount')->willReturn(0);

        $model = new UserTokenModel($this->sqlHelper);
        $this->assertFalse($model->activateByHash($hash));
    }
}
