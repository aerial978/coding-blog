<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\RegistrationEventModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegistrationEventModelTest extends TestCase
{
    private SqlHelperInterface&MockObject $sqlHelper;
    private \PDOStatement&MockObject $statement;

    private RegistrationEventModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlHelper = $this->createMock(SqlHelperInterface::class);
        $this->statement = $this->createMock(\PDOStatement::class);

        $this->model = new RegistrationEventModel($this->sqlHelper);
    }

    public function testRecordEventReturnsTrueWhenOneRowIsInserted(): void
    {
        $email     = 'john@example.com';
        $type      = 'register';
        $userId    = 42;
        $ip        = '127.0.0.1';
        $userAgent = 'PHPUnit';

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO registration_events'),
                [
                    ':email'      => $email,
                    ':type'       => $type,
                    ':user_id'    => $userId,
                    ':ip'         => $ip,
                    ':user_agent' => $userAgent,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = $this->model->recordEvent($email, $type, $userId, $ip, $userAgent);

        $this->assertTrue($result);
    }

    public function testRecordEventReturnsFalseWhenInsertedRowCountIsNotOne(): void
    {
        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $result = $this->model->recordEvent(
            'john@example.com',
            'register',
            null,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertFalse($result);
    }

    public function testCountEventsReturnsIntegerFromFetchColumn(): void
    {
        $ip            = '127.0.0.1';
        $secondsWindow = 3600;

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('SELECT COUNT(*) AS c'),
                $this->callback(function (array $params) use ($ip): bool {
                    return isset($params[':ip'], $params[':cutoff'])
                        && $params[':ip'] === $ip
                        && is_string($params[':cutoff'])
                        && $params[':cutoff'] !== '';
                })
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('4');

        $result = $this->model->countEvents($ip, $secondsWindow);

        $this->assertSame(4, $result);
    }

    public function testDeleteOlderThanReturnsDeletedRowsCount(): void
    {
        $days = 30;

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('DELETE FROM registration_events'),
                [
                    ':days' => $days,
                ]
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(5);

        $result = $this->model->deleteOlderThan($days);

        $this->assertSame(5, $result);
    }
}
