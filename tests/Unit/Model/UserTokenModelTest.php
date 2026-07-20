<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\UserTokenModel;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserTokenModelTest extends TestCase
{
    private SqlHelperInterface&MockObject $sqlHelper;
    private \PDOStatement&MockObject $statement;

    private UserTokenModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlHelper = $this->createMock(SqlHelperInterface::class);
        $this->statement = $this->createMock(\PDOStatement::class);

        $this->model = new UserTokenModel($this->sqlHelper);
    }

    public function testActivateByHashReturnsTrueWhenAtLeastOneRowIsUpdated(): void
    {
        $hash = str_repeat('a', 32);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('UPDATE user u'),
                [':hash' => $hash]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->assertTrue($this->model->activateByHash($hash));
    }

    public function testActivateByHashReturnsFalseWhenNoRowIsUpdated(): void
    {
        $hash = str_repeat('b', 32);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('UPDATE user u'),
                [':hash' => $hash]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $this->assertFalse($this->model->activateByHash($hash));
    }

    public function testHasActiveUnusedTokenReturnsTrueWhenTokenExists(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('SELECT 1'),
                [
                    ':user_id' => 42,
                    ':purpose' => 'confirmation',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('1');

        $this->assertTrue($this->model->hasActiveUnusedToken(42, 'confirmation'));
    }

    public function testHasActiveUnusedTokenReturnsFalseWhenTokenDoesNotExist(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(false);

        $this->assertFalse($this->model->hasActiveUnusedToken(42, 'confirmation'));
    }

    public function testInvalidatePasswordResetTokenReturnsTrueWhenAtLeastOneRowIsUpdated(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('UPDATE user_token'),
                [
                    ':user_id' => 42,
                    ':purpose' => 'password_reset',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->assertTrue($this->model->invalidatePasswordResetToken(42));
    }

    public function testInvalidatePasswordResetTokenReturnsFalseWhenNoRowIsUpdated(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('UPDATE user_token'),
                [
                    ':user_id' => 42,
                    ':purpose' => 'password_reset',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $this->assertFalse($this->model->invalidatePasswordResetToken(42));
    }

    public function testCreateConfirmationTokenReturnsTrueWhenInsertAffectsAtLeastOneRow(): void
    {
        $userId    = 42;
        $hash      = str_repeat('c', 32);
        $expiresAt = new \DateTimeImmutable('2026-03-28 12:00:00');

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user_token'),
                [
                    ':user_id'    => $userId,
                    ':purpose'    => 'confirmation',
                    ':token_hash' => $hash,
                    ':expires_at' => '2026-03-28 12:00:00',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->assertTrue($this->model->createConfirmationToken($userId, $hash, $expiresAt));
    }

    public function testCreatePasswordResetTokenReturnsTrueWhenInsertAffectsAtLeastOneRow(): void
    {
        $userId    = 42;
        $hash      = str_repeat('d', 32);
        $expiresAt = new \DateTimeImmutable('2026-03-28 13:00:00');

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user_token'),
                [
                    ':user_id'    => $userId,
                    ':purpose'    => 'password_reset',
                    ':token_hash' => $hash,
                    ':expires_at' => '2026-03-28 13:00:00',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(2);

        $this->assertTrue($this->model->createPasswordResetToken($userId, $hash, $expiresAt));
    }

    public function testCreateConfirmationTokenThrowsExceptionWhenHashLengthIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token hash must be exactly 32 bytes (SHA-256).');

        $this->sqlHelper
            ->expects($this->never())
            ->method('request');

        $this->model->createConfirmationToken(
            42,
            'short-hash',
            new \DateTimeImmutable('2026-03-28 12:00:00')
        );
    }

    public function testHasActiveUnusedPasswordResetTokenDelegatesToPasswordResetPurpose(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('SELECT 1'),
                [
                    ':user_id' => 42,
                    ':purpose' => 'password_reset',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('1');

        $this->assertTrue($this->model->hasActiveUnusedPasswordResetToken(42));
    }

    public function testFindConfirmationContextByHashReturnsRowWhenFound(): void
    {
        $hash = str_repeat('e', 32);
        $row  = [
            'user_id'     => 42,
            'user_status' => 'inactive',
            'used'        => 0,
            'used_at'     => null,
            'expires_at'  => '2026-03-28 14:00:00',
            'is_expired'  => 0,
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE t.token_hash = :hash'),
                [
                    ':hash'    => $hash,
                    ':purpose' => 'confirmation',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $this->assertSame($row, $this->model->findConfirmationContextByHash($hash));
    }

    public function testFindConfirmationContextByHashReturnsNullWhenNotFound(): void
    {
        $hash = str_repeat('f', 32);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE t.token_hash = :hash'),
                [
                    ':hash'    => $hash,
                    ':purpose' => 'confirmation',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $this->assertNull($this->model->findConfirmationContextByHash($hash));
    }

    public function testFindPasswordResetContextByHashReturnsRowWhenFound(): void
    {
        $hash = str_repeat('g', 32);
        $row  = [
            'user_id'     => 42,
            'user_status' => 'active',
            'used'        => 0,
            'used_at'     => null,
            'expires_at'  => '2026-03-28 15:00:00',
            'is_expired'  => 0,
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE t.token_hash = :hash'),
                [
                    ':hash'    => $hash,
                    ':purpose' => 'password_reset',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $this->assertSame($row, $this->model->findPasswordResetContextByHash($hash));
    }

    public function testFindPasswordResetContextByHashReturnsNullWhenNotFound(): void
    {
        $hash = str_repeat('h', 32);

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE t.token_hash = :hash'),
                [
                    ':hash'    => $hash,
                    ':purpose' => 'password_reset',
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $this->assertNull($this->model->findPasswordResetContextByHash($hash));
    }

    public function testConsumePasswordResetTokenAndUpdatePasswordReturnsTrueWhenUpdateSucceeds(): void
    {
        $hash         = str_repeat('i', 32);
        $passwordHash = 'hashed-password';

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('u.password = :password_hash'),
                [
                    ':password_hash' => $passwordHash,
                    ':hash'          => $hash,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->assertTrue(
            $this->model->consumePasswordResetTokenAndUpdatePassword($hash, $passwordHash)
        );
    }

    public function testConsumePasswordResetTokenAndUpdatePasswordReturnsFalseWhenUpdateFails(): void
    {
        $hash         = str_repeat('j', 32);
        $passwordHash = 'hashed-password';

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('u.password = :password_hash'),
                [
                    ':password_hash' => $passwordHash,
                    ':hash'          => $hash,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $this->assertFalse(
            $this->model->consumePasswordResetTokenAndUpdatePassword($hash, $passwordHash)
        );
    }

    public function testCreateRememberMeTokenReturnsTrueWhenInsertAffectsAtLeastOneRow(): void
    {
        $userId    = 42;
        $hash      = str_repeat('k', 32);
        $expiresAt = new \DateTimeImmutable('2026-03-28 16:00:00');

        $this->sqlHelper
        ->expects($this->once())
        ->method('request')
        ->with(
            $this->stringContains('INSERT INTO user_token'),
            [
                ':user_id'    => $userId,
                ':purpose'    => 'remember_me',
                ':token_hash' => $hash,
                ':expires_at' => '2026-03-28 16:00:00',
            ]
        )
        ->willReturn($this->statement);

        $this->statement
        ->expects($this->once())
        ->method('rowCount')
        ->willReturn(1);

        $this->assertTrue($this->model->createRememberMeToken($userId, $hash, $expiresAt));
    }

    public function testFindRememberMeContextByHashReturnsRowWhenFound(): void
    {
        $hash = str_repeat('l', 32);
        $row  = [
        'user_id'     => 42,
        'user_status' => 'active',
        'used'        => 0,
        'used_at'     => null,
        'expires_at'  => '2026-03-28 17:00:00',
        'is_expired'  => 0,
        ];

        $this->sqlHelper
        ->expects($this->once())
        ->method('request')
        ->with(
            $this->stringContains('WHERE t.token_hash = :hash'),
            [
                ':hash'    => $hash,
                ':purpose' => 'remember_me',
            ]
        )
        ->willReturn($this->statement);

        $this->statement
        ->expects($this->once())
        ->method('fetch')
        ->with(\PDO::FETCH_ASSOC)
        ->willReturn($row);

        $this->assertSame($row, $this->model->findRememberMeContextByHash($hash));
    }

    public function testFindRememberMeContextByHashReturnsNullWhenNotFound(): void
    {
        $hash = str_repeat('m', 32);

        $this->sqlHelper
        ->expects($this->once())
        ->method('request')
        ->with(
            $this->stringContains('WHERE t.token_hash = :hash'),
            [
                ':hash'    => $hash,
                ':purpose' => 'remember_me',
            ]
        )
        ->willReturn($this->statement);

        $this->statement
        ->expects($this->once())
        ->method('fetch')
        ->with(\PDO::FETCH_ASSOC)
        ->willReturn(false);

        $this->assertNull($this->model->findRememberMeContextByHash($hash));
    }

    public function testInvalidateRememberMeTokenReturnsTrueWhenAtLeastOneRowIsUpdated(): void
    {
        $this->sqlHelper
        ->expects($this->once())
        ->method('request')
        ->with(
            $this->stringContains('UPDATE user_token'),
            [
                ':user_id' => 42,
                ':purpose' => 'remember_me',
            ]
        )
        ->willReturn($this->statement);

        $this->statement
        ->expects($this->once())
        ->method('rowCount')
        ->willReturn(1);

        $this->assertTrue($this->model->invalidateRememberMeToken(42));
    }

    public function testInvalidateRememberMeTokenReturnsFalseWhenNoRowIsUpdated(): void
    {
        $this->sqlHelper
        ->expects($this->once())
        ->method('request')
        ->with(
            $this->stringContains('UPDATE user_token'),
            [
                ':user_id' => 42,
                ':purpose' => 'remember_me',
            ]
        )
        ->willReturn($this->statement);

        $this->statement
        ->expects($this->once())
        ->method('rowCount')
        ->willReturn(0);

        $this->assertFalse($this->model->invalidateRememberMeToken(42));
    }
}
