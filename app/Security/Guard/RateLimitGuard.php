<?php

declare(strict_types=1);

namespace App\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\Contract\RateLimiterFactoryInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Security\Guard\Contract\RateLimitGuardInterface;
use Stringable;

final class RateLimitGuard implements RateLimitGuardInterface
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
     *   put_old?: array<string, mixed>|null,
     *   log_ctx?: array<string, mixed>,
     *   silent_success?: bool,
     *   silent_success_flash_type?: 'success'|'error'|'info'|'warning',
     *   silent_success_code?: string,
     *   flags_bag?: string,
     *   set_flags?: array<string, mixed>
     * } $opt
     */
    public function assertAllowed(array $opt): bool
    {
        Logger::getLogger('auth')->info('rl_guard_hit', [
            'file' => __FILE__,
            'key'  => $opt['key'],
        ]);

        $key       = $opt['key'];
        $limit     = $opt['limit'];
        $windowSec = $opt['window_sec'];

        $limiter = $this->rateLimiterFactory->create($key, $limit, $windowSec);

        if ($limiter->isAllowed()) {
            $limiter->recordAttempt();
            return true;
        }

        $retry    = $limiter->getRetryAfter();
        $redirect = $opt['redirect'];

        header('Retry-After: ' . $retry);

        $this->logBlockedRequest($opt, $key, $retry);
        $this->applyFlags($opt);

        $logCtx = $this->buildLogContext($opt, $retry, $key, $limit, $windowSec);

        Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_RATE_LIMITED_DYNAMIC, $logCtx);

        if ($this->isSilentSuccessEnabled($opt)) {
            $this->handleSilentSuccessResponse($opt, $logCtx, $redirect);
            return false;
        }

        $this->handleStandardRateLimitResponse($opt, $retry, $redirect);

        return false;
    }

    /**
     * @param array{
     *   route_for_log?: string
     * } $opt
     */
    private function logBlockedRequest(array $opt, string $key, int $retry): void
    {
        Logger::getLogger('auth')->warning('rate_limit_blocked', [
            'bucket'      => $key,
            'retry_after' => $retry,
            'route'       => $opt['route_for_log']   ?? null,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /**
     * @param array{
     *   flags_bag?: string,
     *   set_flags?: array<string, mixed>
     * } $opt
     */
    private function applyFlags(array $opt): void
    {
        $flagsBag = $this->resolveFlagsBag($opt);

        if (!isset($opt['set_flags'])) {
            Logger::getLogger('auth')->info('rl_no_set_flags', [
                'bag'      => $flagsBag,
                'opt_keys' => array_keys($opt),
            ]);
            return;
        }

        $existing = $this->flash->take($flagsBag, []);
        $existing = is_array($existing) ? $existing : [];

        $merged = $existing + $opt['set_flags'];
        $this->flash->put($flagsBag, $merged);

        Logger::getLogger('auth')->info('rl_set_flags', [
            'bag'      => $flagsBag,
            'existing' => $existing,
            'set'      => $opt['set_flags'],
            'merged'   => $merged,
        ]);
    }

    /**
     * @param array{
     *   flags_bag?: string
     * } $opt
     */
    private function resolveFlagsBag(array $opt): string
    {
        $flagsBag = $opt['flags_bag'] ?? null;

        return is_string($flagsBag) && $flagsBag !== ''
            ? $flagsBag
            : 'security_flags';
    }

    /**
     * @param array{
     *   route_for_log?: string,
     *   log_ctx?: array<string, mixed>
     * } $opt
     * @return array<string, array<int|string, mixed>|bool|float|int|string|Stringable|null>
     */
    private function buildLogContext(
        array $opt,
        int $retry,
        string $key,
        int $limit,
        int $windowSec
    ): array {
        $rawLogCtx = $opt['log_ctx'] ?? [];
        $rawLogCtx = $rawLogCtx + [
            'retry_after' => $retry,
            'bucket'      => $key,
            'route'       => $opt['route_for_log'] ?? null,
            'limit'       => $limit,
            'window_sec'  => $windowSec,
        ];

        /** @var array<string, array<int|string, mixed>|bool|float|int|string|Stringable|null> $logCtx */
        $logCtx = $this->normalizeLogContext($rawLogCtx);

        return $logCtx;
    }

    /**
     * @param array{
     *   silent_success?: bool
     * } $opt
     */
    private function isSilentSuccessEnabled(array $opt): bool
    {
        return !empty($opt['silent_success']);
    }

    /**
     * @param array{
     *   silent_success_flash_type?: 'success'|'error'|'info'|'warning',
     *   silent_success_code?: string,
     *   put_old?: array<string, mixed>|null
     * } $opt
     * @param array<string, array<int|string, mixed>|bool|float|int|string|Stringable|null> $logCtx
     */
    private function handleSilentSuccessResponse(array $opt, array $logCtx, string $redirect): void
    {
        $silentFlashType = $opt['silent_success_flash_type'] ?? 'success';
        $silentCode      = $opt['silent_success_code']       ?? ErrorCode::AUTH_RESEND_EMAIL_SENT;

        $this->flash->add(
            $silentFlashType,
            Logger::logCodeAndGetMessage('auth', 'warning', $silentCode, $logCtx)
        );

        $this->storeOldInput($opt);
        $this->responder->redirect($redirect);
    }

    /**
     * @param array{
     *   flash_type?: 'error'|'success'|'info'|'warning',
     *   message_template_code?: string,
     *   message_replace_token?: string,
     *   put_old?: array<string, mixed>|null
     * } $opt
     */
    private function handleStandardRateLimitResponse(array $opt, int $retry, string $redirect): void
    {
        $timeStr   = $this->formatRetryAfter($retry);
        $flashType = $opt['flash_type'] ?? 'error';

        $templateCode = $opt['message_template_code'] ?? ErrorCode::AUTH_RATE_LIMITED_DYNAMIC;
        $template     = MessageManager::get($templateCode);

        $replaceToken = $opt['message_replace_token'] ?? '{time}';
        $waitMsg      = str_replace($replaceToken, $timeStr, $template);

        $this->flash->add($flashType, $waitMsg);
        $this->storeOldInput($opt);
        $this->responder->redirect($redirect);
    }

    /**
     * @param array{
     *   put_old?: array<string, mixed>|null
     * } $opt
     */
    private function storeOldInput(array $opt): void
    {
        if (!array_key_exists('put_old', $opt) || !is_array($opt['put_old'])) {
            return;
        }

        $this->flash->put('old', $opt['put_old']);
    }

    private function formatRetryAfter(int $retryAfterSec): string
    {
        $min = intdiv($retryAfterSec, 60);
        $sec = $retryAfterSec % 60;

        return $min > 0 ? "{$min} min et {$sec} s" : "{$sec} s";
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, array<int|string, mixed>|bool|float|int|string|Stringable|null>
     */
    private function normalizeLogContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || is_array($value) || $value instanceof Stringable || $value === null) {
                $normalized[$key] = $value;
                continue;
            }

            $normalized[$key] = (string) json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        return $normalized;
    }
}
