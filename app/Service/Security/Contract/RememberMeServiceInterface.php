<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

interface RememberMeServiceInterface
{
    /**
     * Generates and persists a remember me token for the given user,
     * then returns the raw token to be stored in a secure cookie.
     */
    public function createRememberMeToken(int $userId): ?string;

    /**
     * Attempts to restore an authenticated session from a raw remember me token.
     *
     * Returns true if the session was successfully restored, false otherwise.
     */
    public function restoreSessionFromToken(string $rawToken): bool;

    /**
     * Invalidates the active remember me token for the given user.
     */
    public function invalidateRememberMeForUser(int $userId): bool;
}
