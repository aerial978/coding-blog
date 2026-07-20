<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\Contract\RegistrationEventModelInterface;
use App\Security\Contract\RegistrationThrottleServiceInterface;

final class RegistrationThrottleService implements RegistrationThrottleServiceInterface
{
    /**
     * Règles de quotas.
     * Exemple : 3 inscriptions max / heure / IP, 10 / jour / IP.
     */
    private const LIMITS = [
        'per_hour' => 3,
        'per_day'  => 10,
    ];

    public function __construct(
        private RegistrationEventModelInterface $eventModel
    ) {
    }

    /**
     * Vérifie si cette IP peut encore créer un compte.
     *
     * @return array{allowed:bool, reason:?string}
     *   reason: 'hour_quota_exceeded' | 'day_quota_exceeded' | null
     */
    public function checkQuota(string $ip): array
    {
        $hourWindow = 3600;   // 1 heure
        $dayWindow  = 86400;  // 24 heures

        $countHour = $this->eventModel->countEvents($ip, $hourWindow);
        if ($countHour >= self::LIMITS['per_hour']) {
            return ['allowed' => false, 'reason' => 'hour_quota_exceeded'];
        }

        $countDay = $this->eventModel->countEvents($ip, $dayWindow);
        if ($countDay >= self::LIMITS['per_day']) {
            return ['allowed' => false, 'reason' => 'day_quota_exceeded'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * À appeler après une inscription réussie.
     */
    public function recordSuccess(string $email, ?int $userId, string $ip, string $userAgent): bool
    {
        $type = 'registration_attempt';

        return $this->eventModel->recordEvent(
            $email,
            $type,
            $userId,
            $ip,
            $userAgent
        );
    }

    public function cleanup(int $days = 30): int
    {
        return $this->eventModel->deleteOlderThan($days);
    }
}
