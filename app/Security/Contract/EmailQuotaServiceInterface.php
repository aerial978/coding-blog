<?php

declare(strict_types=1);

namespace App\Security\Contract;

interface EmailQuotaServiceInterface
{
    /**
     * @return array{allowed:bool, reason:?string}
     */
    public function checkQuota(string $type, string $email): array;

    public function recordEvent(
        string $email,
        string $type,
        ?int $userId,
        ?string $ip,
        ?string $userAgent
    ): bool;

    public function cleanup(int $days = 30): int;
}
