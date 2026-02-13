<?php

declare(strict_types=1);

namespace App\Security\Guard;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\Logger;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;
use App\Log\LogContextNormalizer;

final class SubmissionDelayGuard
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
     *   context?: array<string,mixed>,
     *   policy?: array<string, array{flash: 'success'|'error'|'info'|'warning', code: string}>,
     *   default?: array{flash: 'success'|'error'|'info'|'warning', code: string},
     *   flags_bag?: string,
     *   set_flags?: array<string,mixed>
     * } $opt
     */
    public function assertPassed(array $opt): bool
    {
        $formId   = $opt['form_id'];
        $redirect = $opt['redirect'];

        $contextBase = is_array($opt['context'] ?? null) ? $opt['context'] : [];

        /** @var array<string, array{flash: 'success'|'error'|'info'|'warning', code: string}> $policy */
        $policy = is_array($opt['policy'] ?? null) ? $opt['policy'] : [];

        $default = $opt['default'] ?? ['flash' => 'error', 'code' => ErrorCode::AUTH_TECHNICAL_ERROR];

        try {
            $this->submissionDelay->assertDelayPassed($formId);
            return true;
        } catch (SuspiciousSubmissionException $e) {
            $reason  = $e->getReason();
            $context = $e->getContext();

            $ctx = $contextBase + [
                'form'    => $context['form']    ?? $formId,
                'elapsed' => $context['elapsed'] ?? null,
                'min'     => $context['min']     ?? null,
                'max'     => $context['max']     ?? null,
            ];

            $decision = $policy[$reason] ?? $default;

            $logCtx = $this->logNormalizer->normalize($ctx + ['reason' => $reason]);

            $flagsBag = is_string($opt['flags_bag'] ?? null) && $opt['flags_bag'] !== ''
                ? (string) $opt['flags_bag']
                : 'security_flags';

            if (isset($opt['set_flags']) && is_array($opt['set_flags'])) {
                $existing = $this->flash->take($flagsBag, []);
                $existing = is_array($existing) ? $existing : [];
                $this->flash->put($flagsBag, $existing + $opt['set_flags']);
            }

            $this->flash->add(
                $decision['flash'],
                Logger::logCodeAndGetMessage('auth', 'warning', $decision['code'], $logCtx)
            );
            $this->responder->redirect($redirect);

            return false;
        }
    }
}
