<?php

declare(strict_types=1);

namespace App\Model\Contract;

interface EmailEventModelInterface
{
    public function countEvents(string $email, string $type, int $windowSeconds): int;

    public function recordEvent(
        string $email,
        string $type,
        ?int $userId,
        string $ip,
        string $userAgent
    ): bool;

    public function deleteOlderThan(int $days): int;
}
