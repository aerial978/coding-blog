<?php

declare(strict_types=1);

namespace App\Model\Contract;

interface RegistrationEventModelInterface
{
    public function countEvents(string $ip, int $windowSeconds): int;

    public function recordEvent(
        string $email,
        string $type,
        ?int $userId,
        string $ip,
        string $userAgent
    ): bool;

    public function deleteOlderThan(int $days): int;

    /**
     * Detaches historical registration events from a user without deleting them.
     */
    public function detachUser(int $userId): void;
}
