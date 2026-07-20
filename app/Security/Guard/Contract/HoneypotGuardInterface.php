<?php

declare(strict_types=1);

namespace App\Security\Guard\Contract;

interface HoneypotGuardInterface
{
    /**
     * @param array{
     *   form: array<string,mixed>,
     *   redirect: string,
     *   flash_type?: 'success'|'error'|'info'|'warning',
     *   code?: string,
     *   log_level?: 'debug'|'info'|'warning'|'error',
     *   log_channel?: string,
     *   context?: array<string,mixed>,
     *   flags_bag?: string,
     *   set_flags?: array<string,mixed>
     * } $opt
     */
    public function assertClean(array $opt): bool;
}
