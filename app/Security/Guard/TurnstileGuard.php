<?php

declare(strict_types=1);

namespace App\Security\Guard;

use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Core\Contract\FlashInterface;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Log\LogContextNormalizer;

final class TurnstileGuard
{
    public function __construct(
        private TurnstileValidatorInterface $turnstile,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private LogContextNormalizer $logNormalizer,
    ) {
    }

    /**
     * Valide Turnstile. En cas d'échec :
     * - empty_token => message clair "Veuillez valider le challenge anti-robot."
     * - autre erreur => AUTH_TECHNICAL_ERROR
     * puis redirect.
     *
     * @param array{
     *   form: array<string,mixed>,
     *   redirect: string,
     *   context?: array<string,mixed>,
     *   token_field?: string,
     *   ip?: string|null,
     *   empty_token_message?: string,
     *   debug_log?: bool
     * } $opt
     */
    public function assertValid(array $opt): bool
    {
        $form        = $opt['form'];
        $redirect    = $opt['redirect'];
        $contextBase = is_array($opt['context'] ?? null) ? $opt['context'] : [];

        $tokenField = is_string($opt['token_field'] ?? null) && $opt['token_field'] !== ''
            ? $opt['token_field']
            : 'cf-turnstile-response';

        $token = $this->strOrEmpty($form[$tokenField] ?? null);

        $ip = array_key_exists('ip', $opt)
            ? (is_string($opt['ip']) ? $opt['ip'] : null)
            : $this->readClientIp();

        if (!empty($opt['debug_log'])) {
            Logger::getLogger('app')->info('turnstile_token_debug', [
                'has_field' => array_key_exists($tokenField, $form),
                'len'       => is_string($form[$tokenField] ?? null) ? strlen((string) $form[$tokenField]) : null,
                'preview'   => is_string($form[$tokenField] ?? null) ? substr((string) $form[$tokenField], 0, 12) : null,
            ]);
        }

        if ($this->turnstile->validate($token, $ip)) {
            return true;
        }

        return $this->handleFailure($redirect, $contextBase);
    }

    /**
     * @param array<string,mixed> $contextBase
     */
    private function handleFailure(string $redirectPath, array $contextBase): bool
    {
        $resp = $this->turnstile->getLastResponse();

        $cfErrors = is_array($resp['error-codes'] ?? null) ? $resp['error-codes'] : null;
        $cfDiag   = is_string($resp['diagnostic'] ?? null) ? $resp['diagnostic'] : null;

        $ctx = $contextBase + [
            'reason'    => 'turnstile_failed',
            'cf_errors' => $cfErrors,
            'cf_diag'   => $cfDiag,
        ];

        $logCtx = $this->logNormalizer->normalize($ctx);

        $isEmptyToken = ($cfDiag === 'empty_token')
            || (is_array($cfErrors) && in_array('missing-input-response', $cfErrors, true));

        if ($isEmptyToken) {
            Logger::getLogger('auth')->warning('Turnstile missing token', $logCtx);
            $this->flash->add('error', 'Veuillez valider le challenge anti-robot.');
            $this->responder->redirect($redirectPath);
            return false;
        }

        $this->flash->add(
            'error',
            Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, $logCtx)
        );
        $this->responder->redirect($redirectPath);

        return false;
    }

    private function strOrEmpty(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function readClientIp(): ?string
    {
        return (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']))
            ? $_SERVER['REMOTE_ADDR']
            : null;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string, array<int|string, mixed>|bool|float|int|string|\Stringable|null>
     */
    private function normalizeLogContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $out[$k] = $this->normalizeLogValue($v);
        }
        return $out;
    }

    /**
     * @return array<int|string, mixed>|bool|float|int|string|\Stringable|null
     */
    private function normalizeLogValue(mixed $value): array|bool|float|int|string|\Stringable|null
    {
        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
            || $value instanceof \Stringable
        ) {
            return $value;
        }

        if (is_array($value)) {
            /** @var array<int|string, mixed> $value */
            return $value;
        }

        if (is_object($value)) {
            return get_debug_type($value);
        }

        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }

        return 'unknown';
    }
}
