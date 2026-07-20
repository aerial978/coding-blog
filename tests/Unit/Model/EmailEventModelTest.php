<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\EmailEventModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EmailEventModelTest extends TestCase
{
    private SqlHelperInterface&MockObject $sqlHelper;
    private \PDOStatement&MockObject $statement;

    private EmailEventModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlHelper = $this->createMock(SqlHelperInterface::class);
        $this->statement = $this->createMock(\PDOStatement::class);

        $this->model = new EmailEventModel($this->sqlHelper);
    }

    public function testRecordEventReturnsTrueWhenOneRowIsInserted(): void
    {
        $email     = 'john@example.com';
        $type      = 'password_reset';
        $userId    = 42;
        $ip        = '127.0.0.1';
        $userAgent = 'PHPUnit';

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('INSERT INTO email_events'),
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
            'confirm_resend',
            null,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertFalse($result);
    }

    public function testCountEventsReturnsIntegerFromFetchColumn(): void
    {
        $email         = 'john@example.com';
        $type          = 'password_reset';
        $secondsWindow = 3600;

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('SELECT COUNT(*) AS c FROM email_events'),
                $this->callback(function (array $params) use ($email, $type): bool {
                    return isset($params[':email'], $params[':type'], $params[':cutoff'])
                        && $params[':email'] === $email
                        && $params[':type']  === $type
                        && is_string($params[':cutoff'])
                        && $params[':cutoff'] !== '';
                })
            )
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('3');

        $result = $this->model->countEvents($email, $type, $secondsWindow);

        $this->assertSame(3, $result);
    }

    public function testDeleteOlderThanReturnsDeletedRowsCount(): void
    {
        $days = 30;

        $this->sqlHelper
            ->expects($this->once())
            ->method('request')
            ->with(
                $this->stringContains('DELETE FROM email_events'),
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
