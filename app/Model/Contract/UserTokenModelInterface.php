<?php

declare(strict_types=1);

namespace App\Model\Contract;

interface UserTokenModelInterface
{
    public function createConfirmationToken(int $userId, string $tokenHash, \DateTimeImmutable $expiresAt): bool;
}
