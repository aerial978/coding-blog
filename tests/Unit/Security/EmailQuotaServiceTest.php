<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Model\Contract\EmailEventModelInterface;
use App\Security\EmailQuotaService;
use PHPUnit\Framework\TestCase;

final class EmailQuotaServiceTest extends TestCase
{
    public function testCheckQuotaAllowsWhenNoRulesForType(): void
    {
        $model = $this->createMock(EmailEventModelInterface::class);

        // Pour un type inconnu, le service ne doit PAS requêter le modèle
        $model->expects(self::never())->method('countEvents');

        $service = new EmailQuotaService($model);

        $result = $service->checkQuota('unknown_type', 'user@example.com');

        self::assertTrue($result['allowed']);
        self::assertNull($result['reason']);
    }

    public function testCheckQuotaBlocksWhenHourlyQuotaExceeded(): void
    {
        $email = 'user@example.com';
        $type  = EmailQuotaService::TYPE_CONFIRM_RESEND;

        $model = $this->createMock(EmailEventModelInterface::class);

        // Quota horaire: limite = 3 ; si countHour = 3 => blocage immédiat
        $model->expects(self::exactly(2))
            ->method('countEvents')
            ->willReturnMap([
                [$email, $type, 3600, 3],
                [$email, $type, 86400, 0],
            ]);

        $service = new EmailQuotaService($model);

        $result = $service->checkQuota($type, $email);

        self::assertFalse($result['allowed']);
        self::assertSame('hour_quota_exceeded', $result['reason']);
    }

    public function testCheckQuotaBlocksWhenDailyQuotaExceeded(): void
    {
        $email = 'user@example.com';
        $type  = EmailQuotaService::TYPE_CONFIRM_RESEND;

        $model = $this->createMock(EmailEventModelInterface::class);

        // Heure OK (2 < 3), mais jour dépassé (10 >= 10)
        $model->expects(self::exactly(2))
            ->method('countEvents')
            ->willReturnMap([
                [$email, $type, 3600, 2],
                [$email, $type, 86400, 10],
            ]);

        $service = new EmailQuotaService($model);

        $result = $service->checkQuota($type, $email);

        self::assertFalse($result['allowed']);
        self::assertSame('day_quota_exceeded', $result['reason']);
    }

    public function testCheckQuotaAllowsWhenBelowLimits(): void
    {
        $email = 'user@example.com';
        $type  = EmailQuotaService::TYPE_CONFIRM_RESEND;

        $model = $this->createMock(EmailEventModelInterface::class);

        $model->expects(self::exactly(2))
            ->method('countEvents')
            ->willReturnMap([
                [$email, $type, 3600, 0],
                [$email, $type, 86400, 0],
            ]);

        $service = new EmailQuotaService($model);

        $result = $service->checkQuota($type, $email);

        self::assertTrue($result['allowed']);
        self::assertNull($result['reason']);
    }

    public function testRecordEventUsesSafeDefaultsWhenIpAndUserAgentNull(): void
    {
        $email  = 'user@example.com';
        $type   = EmailQuotaService::TYPE_CONFIRM_RESEND;
        $userId = 123;

        $model = $this->createMock(EmailEventModelInterface::class);

        $model->expects(self::once())
            ->method('recordEvent')
            ->with($email, $type, $userId, '0.0.0.0', 'unknown')
            ->willReturn(true);

        $service = new EmailQuotaService($model);

        self::assertTrue($service->recordEvent($email, $type, $userId, null, null));
    }

    public function testCleanupDelegatesToModel(): void
    {
        $model = $this->createMock(EmailEventModelInterface::class);

        $model->expects(self::once())
            ->method('deleteOlderThan')
            ->with(30)
            ->willReturn(7);

        $service = new EmailQuotaService($model);

        self::assertSame(7, $service->cleanup());
    }
}
