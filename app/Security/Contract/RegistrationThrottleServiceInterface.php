<?php

declare(strict_types=1);

namespace App\Security\Contract;

interface RegistrationThrottleServiceInterface
{
    /** @return array{allowed:bool, reason:?string} */
    public function checkQuota(string $ip): array;

    public function recordSuccess(string $email, ?int $userId, string $ip, string $userAgent): bool;
}
