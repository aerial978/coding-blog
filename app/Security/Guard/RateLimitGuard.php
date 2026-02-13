<?php

declare(strict_types=1);

namespace App\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;

final class RateLimitGuard
{
    public function __construct(
        private RateLimiterFactoryInterface $rateLimiterFactory,
        private FlashInterface $flash,
        private ResponderInterface $responder,
    ) {
    }

    /**
     * Vérifie un rate limit et applique la politique d'échec :
     * - ajoute Retry-After
     * - flash message
     * - (optionnel) put old
     * - redirect
     *
     * Extension rétrocompatible :
     * - Par défaut : comportement actuel (message dynamique + flash + redirect)
     * - Opt-in : "silent_success" pour les flux anti-énumération (ex. resend)
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
     *   silent_success_code?: string
     * } $opt
     */
    public function assertAllowed(array $opt): bool
    {
        $key       = $opt['key'];
        $limit     = $opt['limit'];
        $windowSec = $opt['window_sec'];
        $redirect  = $opt['redirect'];

        $limiter = $this->rateLimiterFactory->create($key, $limit, $windowSec);

        $allowed = $limiter->isAllowed();

        if ($allowed) {
            $limiter->recordAttempt();
            return true;
        }

        $retry = $limiter->getRetryAfter();
        header('Retry-After: ' . $retry);

        Logger::getLogger('auth')->warning('rate_limit_blocked', [
            'bucket'      => $key,
            'retry_after' => $retry,
            'route'       => $opt['route_for_log'] ?? null,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $flagsBag = is_string($opt['flags_bag'] ?? null) && $opt['flags_bag'] !== ''
            ? (string) $opt['flags_bag']
            : 'security_flags';

        if (isset($opt['set_flags']) && is_array($opt['set_flags'])) {
            // fusionner avec l’existant pour éviter d’écraser d’autres flags
            $existing = $this->flash->take($flagsBag, []);
            $existing = is_array($existing) ? $existing : [];
            
            $this->flash->put($flagsBag, $existing + $opt['set_flags']);
        }

        // Log technique (inchangé)
        $logCtx = $opt['log_ctx'] ?? [];
        $logCtx = $logCtx + [
            'retry_after' => $retry,
            'bucket'      => $key,
            'route'       => $opt['route_for_log'] ?? null,
            'limit'       => $limit,
            'window_sec'  => $windowSec,
        ];

        Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_RATE_LIMITED_DYNAMIC, $logCtx);

        // =========================
        // OPT-IN : succès silencieux
        // =========================
        if (!empty($opt['silent_success'])) {
            $silentFlashType = $opt['silent_success_flash_type'] ?? 'success';
            $silentCode      = $opt['silent_success_code'] ?? ErrorCode::AUTH_RESEND_EMAIL_SENT;

            $this->flash->add(
                $silentFlashType,
                Logger::logCodeAndGetMessage('auth', 'warning', $silentCode, $logCtx)
            );

            // On ne remet PAS "old" ici par défaut : anti-énumération + cohérence UX.
            // Si vous voulez quand même, passez put_old comme d'habitude.
            if (array_key_exists('put_old', $opt) && is_array($opt['put_old'])) {
                $this->flash->put('old', $opt['put_old']);
            }

            $this->responder->redirect($redirect);
            return false;
        }

        // =========================
        // DEFAULT : comportement actuel (register/login/etc.)
        // =========================
        $timeStr = $this->formatRetryAfter($retry);

        $flashType = $opt['flash_type'] ?? 'error';

        // Par défaut : message dynamique commun (AUTH_RATE_LIMITED_DYNAMIC)
        $templateCode = $opt['message_template_code'] ?? ErrorCode::AUTH_RATE_LIMITED_DYNAMIC;
        $template     = MessageManager::get($templateCode);

        // Token remplaçable dans le template (par défaut "{time}")
        $replaceToken = $opt['message_replace_token'] ?? '{time}';
        $waitMsg      = str_replace($replaceToken, $timeStr, $template);

        // Flash message
        $this->flash->add($flashType, $waitMsg);

        // Old input éventuel (register, login, etc.)
        if (array_key_exists('put_old', $opt) && is_array($opt['put_old'])) {
            $this->flash->put('old', $opt['put_old']);
        }

        $this->responder->redirect($redirect);
        return false;
    }

    private function formatRetryAfter(int $retryAfterSec): string
    {
        $min = intdiv($retryAfterSec, 60);
        $sec = $retryAfterSec % 60;

        return $min > 0 ? "{$min} min et {$sec} s" : "{$sec} s";
    }
}
