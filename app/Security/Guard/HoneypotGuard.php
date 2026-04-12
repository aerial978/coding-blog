<?php

declare(strict_types=1);

namespace App\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;
use App\Security\Guard\Contract\HoneypotGuardInterface;

final class HoneypotGuard implements HoneypotGuardInterface
{
    public function __construct(
        private HoneypotValidatorInterface $honeypot,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private LogContextNormalizer $logNormalizer,
    ) {
    }

    /**
     * Honeypot générique.
     *
     * En cas de suspicion : flash + redirect, retourne false.
     * Sinon : retourne true.
     *
     * @param array{
     *   form: array<string, mixed>,
     *   redirect: string,
     *   flash_type?: 'success'|'error'|'info'|'warning',
     *   code?: string,
     *   log_level?: 'debug'|'info'|'warning'|'error',
     *   log_channel?: string,
     *   context?: array<string, mixed>,
     *   flags_bag?: string,
     *   set_flags?: array<string, mixed>
     * } $opt
     */
    public function assertClean(array $opt): bool
    {
        /** @var array<string, mixed> $form */
        $form = $opt['form'];
        $redirect = $opt['redirect'];

        try {
            $this->honeypot->assertClean($form);

            return true;
        } catch (SuspiciousSubmissionException $e) {
            $this->handleSuspiciousSubmission($opt, $redirect);

            return false;
        }
    }

    /**
     * @param array{
     *   flash_type?: 'success'|'error'|'info'|'warning',
     *   code?: string,
     *   log_level?: 'debug'|'info'|'warning'|'error',
     *   log_channel?: string,
     *   context?: array<string, mixed>,
     *   flags_bag?: string,
     *   set_flags?: array<string, mixed>
     * } $opt
     */
    private function handleSuspiciousSubmission(array $opt, string $redirect): void
    {
        $flashType = $this->resolveFlashType($opt);
        $code = $this->resolveCode($opt);
        $logLevel = $this->resolveLogLevel($opt);
        $logChannel = $this->resolveLogChannel($opt);
        $contextBase = $this->resolveContextBase($opt);

        $ctx = $contextBase + [
            'reason' => 'honeypot',
        ];

        $logCtx = $this->logNormalizer->normalize($ctx);

        $this->flash->add(
            $flashType,
            Logger::logCodeAndGetMessage($logChannel, $logLevel, $code, $logCtx)
        );

        $this->applyFlags($opt);
        $this->logFlags($opt);

        $this->responder->redirect($redirect);
    }

    /**
     * @param array{
     *   flash_type?: 'success'|'error'|'info'|'warning'
     * } $opt
     */
    private function resolveFlashType(array $opt): string
    {
        $flashType = $opt['flash_type'] ?? null;

        return is_string($flashType)
            ? $flashType
            : 'error';
    }

    /**
     * @param array{
     *   code?: string
     * } $opt
     */
    private function resolveCode(array $opt): string
    {
        $code = $opt['code'] ?? null;

        return is_string($code) && $code !== ''
            ? $code
            : ErrorCode::AUTH_TECHNICAL_ERROR;
    }

    /**
     * @param array{
     *   log_level?: 'debug'|'info'|'warning'|'error'
     * } $opt
     */
    private function resolveLogLevel(array $opt): string
    {
        $logLevel = $opt['log_level'] ?? null;

        return is_string($logLevel)
            ? $logLevel
            : 'warning';
    }

    /**
     * @param array{
     *   log_channel?: string
     * } $opt
     */
    private function resolveLogChannel(array $opt): string
    {
        $logChannel = $opt['log_channel'] ?? null;

        return is_string($logChannel) && $logChannel !== ''
            ? $logChannel
            : 'auth';
    }

    /**
     * @param array{
     *   context?: array<string, mixed>
     * } $opt
     * @return array<string, mixed>
     */
    private function resolveContextBase(array $opt): array
    {
        return is_array($opt['context'] ?? null) ? $opt['context'] : [];
    }

    /**
     * @param array{
     *   flags_bag?: string,
     *   set_flags?: array<string, mixed>
     * } $opt
     */
    private function applyFlags(array $opt): void
    {
        if (!isset($opt['set_flags'])) {
            return;
        }

        $flagsBag = $this->resolveFlagsBag($opt);
        $existing = $this->flash->take($flagsBag, []);
        $existing = is_array($existing) ? $existing : [];

        $this->flash->put($flagsBag, $existing + $opt['set_flags']);
    }

    /**
     * @param array{
     *   set_flags?: array<string, mixed>
     * } $opt
     */
    private function logFlags(array $opt): void
    {
        Logger::getLogger('auth')->info('security_flags_set', [
            'guard' => 'honeypot',
            'flags' => $opt['set_flags'] ?? null,
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
}