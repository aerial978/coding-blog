<?php

declare(strict_types=1);

namespace App\Model\Contract;

interface UserTokenModelInterface
{
    public function createConfirmationToken(int $userId, string $tokenHash, \DateTimeImmutable $expiresAt): bool;

    public function createPasswordResetToken(int $userId, string $tokenHash, \DateTimeImmutable $expiresAt): bool;

    public function createRememberMeToken(int $userId, string $tokenHash, \DateTimeImmutable $expiresAt): bool;

    public function hasActiveUnusedPasswordResetToken(int $userId): bool;

    public function invalidatePasswordResetToken(int $userId): bool;

    public function invalidateRememberMeToken(int $userId): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function findConfirmationContextByHash(string $hashBinary32): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findPasswordResetContextByHash(string $hashBinary32): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findRememberMeContextByHash(string $hashBinary32): ?array;

    public function activateByHash(string $hashBinary32): bool;

    /**
     * Met à jour user.password et marque le token password_reset comme used=1
     * en une seule requête (JOIN) pour éviter les incohérences.
     */
    public function consumePasswordResetTokenAndUpdatePassword(string $hashBinary32, string $passwordHash): bool;
}
