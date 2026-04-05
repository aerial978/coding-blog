<?php

declare(strict_types=1);

namespace App\Security\Guard\Contract;

interface RateLimitGuardInterface
{
    /**
     * Vérifie un rate limit et applique la politique d'échec.
     *
     * @param array{
     *   key: string,
     *   limit: int,
     *   window_sec: int,
     *   redirect: string,
     *   route_for_log?: string,
     *   flash_type?: 'error'|'success'|'info'|'warning',
     *   message_code?: string,
     *   message_template_code?: string,
     *   message_replace_token?: string,
     *   put_old?: array<string,mixed>|null,
     *   log_ctx?: array<string,mixed>,
     *   silent_success?: bool,
     *   silent_success_flash_type?: 'success'|'error'|'info'|'warning',
     *   silent_success_code?: string,
     *   flags_bag?: string,
     *   set_flags?: array<string,mixed>
     * } $opt
     */
    public function assertAllowed(array $opt): bool;
}
