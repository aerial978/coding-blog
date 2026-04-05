<?php

declare(strict_types=1);

namespace App\Security\Guard\Contract;

interface SubmissionDelayGuardInterface
{
    /**
     * Vérifie que le délai de soumission est respecté.
     *
     * @param array{
     *   form_id: string,
     *   redirect: string,
     *   context?: array<string, mixed>,
     *   policy?: array<string, array{flash: 'success'|'error'|'info'|'warning', code: string}>,
     *   default?: array{flash: 'success'|'error'|'info'|'warning', code: string},
     *   flags_bag?: string,
     *   set_flags?: array<string, mixed>,
     *   min_sec?: int|string,
     *   max_sec?: int|string
     * } $opt
     */
    public function assertPassed(array $opt): bool;
}
