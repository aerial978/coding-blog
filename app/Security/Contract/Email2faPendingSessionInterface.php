<?php

declare(strict_types=1);

namespace App\Security\Contract;

interface Email2faPendingSessionInterface
{
    public function start(int $userId, bool $rememberMeRequested): void;

    public function hasPending(): bool;

    public function getPendingUserId(): ?int;

    public function wasRememberMeRequested(): bool;

    public function isExpired(): bool;

    public function clear(): void;
}
