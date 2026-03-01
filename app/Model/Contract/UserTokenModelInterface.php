<?php

declare(strict_types=1);

namespace App\Model\Contract;

interface UserTokenModelInterface
{
    public function createConfirmationToken(int $userId, string $tokenHash, \DateTimeImmutable $expiresAt): bool;

    public function createPasswordResetToken(int $userId, string $tokenHash, \DateTimeImmutable $expiresAt): bool;

    public function hasActiveUnusedPasswordResetToken(int $userId): bool;

    public function invalidatePasswordResetToken(int $userId): bool;

    public function findPasswordResetContextByHash(string $hashBinary32): ?array;
}
