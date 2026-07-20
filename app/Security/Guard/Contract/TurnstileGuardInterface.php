<?php

declare(strict_types=1);

namespace App\Security\Guard\Contract;

interface TurnstileGuardInterface
{
    /**
     * Valide le challenge Turnstile.
     *
     * @param array{
     *   form: array<string,mixed>,
     *   redirect: string,
     *   context?: array<string,mixed>,
     *   token_field?: string,
     *   ip?: string|null,
     *   debug_log?: bool
     * } $opt
     */
    public function assertValid(array $opt): bool;
}
