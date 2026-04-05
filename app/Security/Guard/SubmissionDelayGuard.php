<?php

declare(strict_types=1);

namespace App\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Log\LogContextNormalizer;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;
use App\Security\Guard\Contract\SubmissionDelayGuardInterface;

final class SubmissionDelayGuard implements SubmissionDelayGuardInterface
{
    public function __construct(
        private SubmissionDelayValidatorInterface $submissionDelay,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private LogContextNormalizer $logNormalizer,
    ) {
    }

    /**
     * Vérifie que le délai de soumission est respecté.
     * En cas de violation : flash + redirect, et retourne false.
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
    public function assertPassed(array $opt): bool
    {
        $formId      = $opt['form_id'];
        $redirect    = $opt['redirect'];
        $contextBase = $this->resolveContextBase($opt);
        $policy      = $this->resolvePolicy($opt);
        $default     = $this->resolveDefaultDecision($opt);

        try {
            $min = $this->parseNullableInt($opt, 'min_sec');
            $max = $this->parseNullableInt($opt, 'max_sec');

            $this->submissionDelay->assertDelayPassed($formId, $min, $max);

            return true;
        } catch (SuspiciousSubmissionException $e) {
            $this->handleSuspiciousSubmission($opt, $e, $formId, $redirect, $contextBase, $policy, $default);

            return false;
        }
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
     *   policy?: array<string, array{flash: 'success'|'error'|'info'|'warning', code: string}>
     * } $opt
     * @return array<string, array{flash: 'success'|'error'|'info'|'warning', code: string}>
     */
    private function resolvePolicy(array $opt): array
    {
        return is_array($opt['policy'] ?? null) ? $opt['policy'] : [];
    }

    /**
     * @param array{
     *   default?: array{flash: 'success'|'error'|'info'|'warning', code: string}
     * } $opt
     * @return array{flash: 'success'|'error'|'info'|'warning', code: string}
     */
    private function resolveDefaultDecision(array $opt): array
    {
        $default = $opt['default'] ?? null;

        if (is_array($default)) {
            return $default;
        }

        return [
            'flash' => 'error',
            'code'  => ErrorCode::AUTH_TECHNICAL_ERROR,
        ];
    }

    /**
     * @param array<string, mixed> $opt
     */
    private function parseNullableInt(array $opt, string $key): ?int
    {
        if (!isset($opt[$key])) {
            return null;
        }

        $value = $opt[$key];

        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array{
     *   flags_bag?: string,
     *   set_flags?: array<string, mixed>
     * } $opt
     * @param array<string, mixed> $contextBase
     * @param array<string, array{flash: 'success'|'error'|'info'|'warning', code: string}> $policy
     * @param array{flash: 'success'|'error'|'info'|'warning', code: string} $default
     */
    private function handleSuspiciousSubmission(
        array $opt,
        SuspiciousSubmissionException $exception,
        string $formId,
        string $redirect,
        array $contextBase,
        array $policy,
        array $default
    ): void {
        $reason   = $exception->getReason();
        $context  = $exception->getContext();
        $ctx      = $this->buildExceptionContext($contextBase, $context, $formId);
        $decision = $policy[$reason] ?? $default;
        $logCtx   = $this->logNormalizer->normalize($ctx + ['reason' => $reason]);

        $this->applyFlags($opt);

        $this->flash->add(
            $decision['flash'],
            Logger::logCodeAndGetMessage('auth', 'warning', $decision['code'], $logCtx)
        );

        $this->responder->redirect($redirect);
    }

    /**
     * @param array<string, mixed> $contextBase
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildExceptionContext(array $contextBase, array $context, string $formId): array
    {
        return $contextBase + [
            'form'    => $context['form']    ?? $formId,
            'elapsed' => $context['elapsed'] ?? null,
            'min'     => $context['min']     ?? null,
            'max'     => $context['max']     ?? null,
        ];
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
