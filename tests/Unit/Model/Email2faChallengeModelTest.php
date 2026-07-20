<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Email2faChallengeModel;
use App\Model\Entity\Email2faChallengeEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class Email2faChallengeModelTest extends TestCase
{
    private SqlHelperInterface&MockObject $sqlHelper;
    private \PDOStatement&MockObject $statement;

    private Email2faChallengeModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlHelper = $this->createMock(SqlHelperInterface::class);
        $this->statement = $this->createMock(\PDOStatement::class);

        $this->model = new Email2faChallengeModel($this->sqlHelper);
    }

    public function testCreateChallengeReturnsInsertedIdWhenInsertSucceeds(): void
    {
        $userId    = 42;
        $codeHash  = 'hashed-code';
        $expiresAt = '2026-05-16 15:00:00';

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO user_2fa_challenge'),
                [
                    ':user_id'    => $userId,
                    ':code_hash'  => $codeHash,
                    ':expires_at' => $expiresAt,
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
            ->willReturn(15);

        $result = $this->model->createChallenge(
            $userId,
            $codeHash,
            $expiresAt
        );

        $this->assertSame(15, $result);
    }

    public function testCreateChallengeReturnsZeroWhenInsertFails(): void
    {
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

        $result = $this->model->createChallenge(
            42,
            'hashed-code',
            '2026-05-16 15:00:00'
        );

        $this->assertSame(0, $result);
    }

    public function testFindActiveChallengeByUserIdReturnsHydratedEntityWhenFound(): void
    {
        $row = [
            'id'           => 10,
            'user_id'      => 42,
            'code_hash'    => 'hashed-code',
            'expires_at'   => '2026-05-16 15:00:00',
            'used'         => 0,
            'used_at'      => null,
            'attempts'     => 1,
            'created_at'   => '2026-05-16 14:00:00',
            'last_sent_at' => '2026-05-16 14:00:00',
        ];

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE user_id = :user_id'),
                [
                    ':user_id' => 42,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn($row);

        $result = $this->model->findActiveChallengeByUserId(42);

        $this->assertInstanceOf(Email2faChallengeEntity::class, $result);
        $this->assertSame(10, $result->getId());
        $this->assertSame(42, $result->getUserId());
        $this->assertSame('hashed-code', $result->getCodeHash());
        $this->assertSame(1, $result->getAttempts());
        $this->assertFalse($result->isUsed());
    }

    public function testFindActiveChallengeByUserIdReturnsNullWhenNotFound(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetch')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->model->findActiveChallengeByUserId(42);

        $this->assertNull($result);
    }

    public function testIncrementAttemptsReturnsTrueWhenUpdateSucceeds(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('SET attempts = attempts + 1'),
                [
                    ':id' => 10,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->assertTrue(
            $this->model->incrementAttempts(10)
        );
    }

    public function testMarkAsUsedReturnsTrueWhenUpdateSucceeds(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('SET'),
                [
                    ':id' => 10,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->assertTrue(
            $this->model->markAsUsed(10)
        );
    }

    public function testInvalidateActiveChallengesForUserReturnsTrue(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('WHERE user_id = :user_id'),
                [
                    ':user_id' => 42,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->never())
            ->method('rowCount');

        $this->assertTrue(
            $this->model->invalidateActiveChallengesForUser(42)
        );
    }

    public function testUpdateLastSentAtReturnsTrueWhenUpdateSucceeds(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('SET last_sent_at = NOW()'),
                [
                    ':id' => 10,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->assertTrue(
            $this->model->updateLastSentAt(10)
        );
    }
}
