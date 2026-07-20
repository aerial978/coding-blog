<?php

declare(strict_types=1);

namespace App\Security\Contract;

interface RememberMeCookieManagerInterface
{
    /**
     * Creates the persistent remember me cookie.
     */
    public function createCookie(string $rawToken): void;

    /**
     * Expires the remember me cookie immediately.
     */
    public function expireCookie(): void;

    /**
     * Returns the remember me cookie value if present and non-empty.
     */
    public function getCookieValue(): ?string;
}
