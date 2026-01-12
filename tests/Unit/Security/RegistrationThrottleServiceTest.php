<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Model\Contract\RegistrationEventModelInterface;
use App\Security\RegistrationThrottleService;
use PHPUnit\Framework\TestCase;

final class RegistrationThrottleServiceTest extends TestCase
{
    public function testCheckQuotaAllowsWhenBelowLimits(): void
    {
        $ip = '127.0.0.1';

        $eventModel = $this->createMock(RegistrationEventModelInterface::class);

        $eventModel
            ->expects(self::exactly(2))
            ->method('countEvents')
            ->willReturnMap([
                [$ip, 3600, 1],
                [$ip, 86400, 5],
            ]);

        $service = new RegistrationThrottleService($eventModel);

        $result = $service->checkQuota($ip);

        self::assertTrue($result['allowed']);
        self::assertNull($result['reason']);
    }

    public function testCheckQuotaBlocksWhenHourlyQuotaExceeded(): void
    {
        $ip = '127.0.0.2';

        $eventModel = $this->createMock(RegistrationEventModelInterface::class);

        $eventModel
            ->expects(self::once())
            ->method('countEvents')
            ->with($ip, 3600)
            ->willReturn(3);

        $service = new RegistrationThrottleService($eventModel);

        $result = $service->checkQuota($ip);

        self::assertFalse($result['allowed']);
        self::assertSame('hour_quota_exceeded', $result['reason']);
    }

    public function testCheckQuotaBlocksWhenDailyQuotaExceeded(): void
    {
        $ip = '127.0.0.3';

        $eventModel = $this->createMock(RegistrationEventModelInterface::class);

        $eventModel
            ->expects(self::exactly(2))
            ->method('countEvents')
            ->willReturnMap([
                [$ip, 3600, 2],
                [$ip, 86400, 10],
            ]);

        $service = new RegistrationThrottleService($eventModel);

        $result = $service->checkQuota($ip);

        self::assertFalse($result['allowed']);
        self::assertSame('day_quota_exceeded', $result['reason']);
    }

    public function testRecordSuccessDelegatesToModel(): void
    {
        $email     = 'user@example.com';
        $userId    = 42;
        $ip        = '192.168.0.10';
        $userAgent = 'UnitTest UA';

        $eventModel = $this->createMock(RegistrationEventModelInterface::class);

        $eventModel
            ->expects(self::once())
            ->method('recordEvent')
            ->with($email, 'registration_attempt', $userId, $ip, $userAgent)
            ->willReturn(true);

        $service = new RegistrationThrottleService($eventModel);

        self::assertTrue($service->recordSuccess($email, $userId, $ip, $userAgent));
    }

    public function testCleanupDefaultRetention(): void
    {
        $eventModel = $this->createMock(RegistrationEventModelInterface::class);

        $eventModel
            ->expects(self::once())
            ->method('deleteOlderThan')
            ->with(30)
            ->willReturn(5);

        $service = new RegistrationThrottleService($eventModel);

        self::assertSame(5, $service->cleanup());
    }
}
