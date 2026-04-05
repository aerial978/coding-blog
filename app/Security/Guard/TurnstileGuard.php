<?php

declare(strict_types=1);

namespace App\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Security\Contract\TurnstileValidatorInterface;
use App\Security\Guard\Contract\TurnstileGuardInterface;

final class TurnstileGuard implements TurnstileGuardInterface
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
     * - autre erreur => AUTH_TECHNICAL_ERROR
     * puis redirect.
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
    public function assertValid(array $opt): bool
    {
        $form        = $opt['form'];
        $redirect    = $opt['redirect'];
        $contextBase = $this->resolveContextBase($opt);
        $tokenField  = $this->resolveTokenField($opt);
        $token       = $this->extractToken($form, $tokenField);
        $ip          = $this->resolveIp($opt);

        $this->logDebugTokenIfEnabled($opt, $form, $tokenField);

        if ($this->turnstile->validate($token, $ip)) {
            return true;
        }

        return $this->handleFailure($redirect, $contextBase);
    }

    /**
     * @param array{
     *   context?: array<string,mixed>
     * } $opt
     * @return array<string,mixed>
     */
    private function resolveContextBase(array $opt): array
    {
        return is_array($opt['context'] ?? null) ? $opt['context'] : [];
    }

    /**
     * @param array{
     *   token_field?: string
     * } $opt
     */
    private function resolveTokenField(array $opt): string
    {
        $tokenField = $opt['token_field'] ?? null;

        return is_string($tokenField) && $tokenField !== ''
            ? $tokenField
            : 'cf-turnstile-response';
    }

    /**
     * @param array<string,mixed> $form
     */
    private function extractToken(array $form, string $tokenField): string
    {
        return $this->strOrEmpty($form[$tokenField] ?? null);
    }

    /**
     * @param array{
     *   ip?: string|null
     * } $opt
     */
    private function resolveIp(array $opt): ?string
    {
        if (array_key_exists('ip', $opt)) {
            return is_string($opt['ip']) ? $opt['ip'] : null;
        }

        return $this->readClientIp();
    }

    /**
     * @param array{
     *   debug_log?: bool
     * } $opt
     * @param array<string,mixed> $form
     */
    private function logDebugTokenIfEnabled(array $opt, array $form, string $tokenField): void
    {
        if (empty($opt['debug_log'])) {
            return;
        }

        Logger::getLogger('app')->info('turnstile_token_debug', [
            'has_field' => array_key_exists($tokenField, $form),
            'len'       => $this->extractTokenLength($form, $tokenField),
            'preview'   => $this->extractTokenPreview($form, $tokenField),
        ]);
    }

    /**
     * @param array<string,mixed> $form
     */
    private function extractTokenLength(array $form, string $tokenField): ?int
    {
        $rawValue = $form[$tokenField] ?? null;

        if (!is_string($rawValue)) {
            return null;
        }

        return strlen($rawValue);
    }

    /**
     * @param array<string,mixed> $form
     */
    private function extractTokenPreview(array $form, string $tokenField): ?string
    {
        $rawValue = $form[$tokenField] ?? null;

        if (!is_string($rawValue)) {
            return null;
        }

        return substr($rawValue, 0, 12);
    }

    /**
     * @param array<string,mixed> $contextBase
     */
    private function handleFailure(string $redirectPath, array $contextBase): bool
    {
        $resp     = $this->turnstile->getLastResponse();
        $cfErrors = $this->extractErrorCodes($resp);
        $cfDiag   = $this->extractDiagnostic($resp);
        $logCtx   = $this->buildFailureLogContext($contextBase, $cfErrors, $cfDiag);

        if ($this->isEmptyTokenFailure($cfErrors, $cfDiag)) {
            $this->handleMissingTokenFailure($redirectPath, $logCtx);
            return false;
        }

        $this->handleTechnicalFailure($redirectPath, $logCtx);

        return false;
    }

    /**
     * @param array<string,mixed>|null $response
     * @return array<int,string>|null
     */
    private function extractErrorCodes(?array $response): ?array
    {
        if ($response === null) {
            return null;
        }

        $errorCodes = $response['error-codes'] ?? null;
        if (!is_array($errorCodes)) {
            return null;
        }

        /** @var array<int, string> $filtered */
        $filtered = array_values(array_filter($errorCodes, 'is_string'));

        return $filtered === [] ? null : $filtered;
    }

    /**
     * @param array<string,mixed>|null $response
     */
    private function extractDiagnostic(?array $response): ?string
    {
        if ($response === null) {
            return null;
        }

        $diagnostic = $response['diagnostic'] ?? null;

        return is_string($diagnostic) ? $diagnostic : null;
    }

    /**
     * @param array<string,mixed> $contextBase
     * @param array<int,string>|null $cfErrors
     * @return array<string, array<int|string, mixed>|bool|float|int|string|\Stringable|null>
     */
    private function buildFailureLogContext(array $contextBase, ?array $cfErrors, ?string $cfDiag): array
    {
        $ctx = $contextBase + [
            'reason'    => 'turnstile_failed',
            'cf_errors' => $cfErrors,
            'cf_diag'   => $cfDiag,
        ];

        return $this->logNormalizer->normalize($ctx);
    }

    /**
     * @param array<int,string>|null $cfErrors
     */
    private function isEmptyTokenFailure(?array $cfErrors, ?string $cfDiag): bool
    {
        if ($cfDiag === 'empty_token') {
            return true;
        }

        return is_array($cfErrors) && in_array('missing-input-response', $cfErrors, true);
    }

    /**
     * @param array<string, array<int|string, mixed>|bool|float|int|string|\Stringable|null> $logCtx
     */
    private function handleMissingTokenFailure(string $redirectPath, array $logCtx): void
    {
        Logger::getLogger('auth')->warning('Turnstile missing token', $logCtx);
        $this->flash->add('error', 'Veuillez valider le challenge anti-robot.');
        $this->responder->redirect($redirectPath);
    }

    /**
     * @param array<string, array<int|string, mixed>|bool|float|int|string|\Stringable|null> $logCtx
     */
    private function handleTechnicalFailure(string $redirectPath, array $logCtx): void
    {
        $this->flash->add(
            'error',
            Logger::logCodeAndGetMessage('auth', 'warning', ErrorCode::AUTH_TECHNICAL_ERROR, $logCtx)
        );
        $this->responder->redirect($redirectPath);
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
}
