<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Contract\SessionInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;

final class SubmissionDelayValidator implements SubmissionDelayValidatorInterface
{
    private SessionInterface $session;
    private int $defaultMinSeconds;
    private int $defaultMaxSeconds;

    public function __construct(
        SessionInterface $session,
        int $defaultMinSeconds = 10,
        int $defaultMaxSeconds = 1800
    ) {
        $this->session           = $session;
        $this->defaultMinSeconds = $defaultMinSeconds;
        $this->defaultMaxSeconds = $defaultMaxSeconds;
    }

    /**
     * Marque le moment où le formulaire est affiché (GET).
     */
    public function markFormStart(string $formId): void
    {
        $this->session->set($this->getKey($formId), time());

        // Réinitialise le flag "min ok" à chaque affichage du formulaire
        $this->session->remove($this->getMinOkKey($formId));
    }

    /**
     * Vérifie que le temps est compris entre min et max.
     *
     * - Si elapsed < min  → SuspiciousSubmissionException('min_delay_not_met', ...)
     * - Si elapsed > max  → SuspiciousSubmissionException('max_delay_exceeded', ...)
     *
     * @throws SuspiciousSubmissionException
     */
    public function assertDelayPassed(
        string $formId,
        ?int $minSeconds = null,
        ?int $maxSeconds = null
    ): void {
        $start = $this->session->get($this->getKey($formId));

        // Si aucune trace du GET : on ne bloque pas (comportement neutre)
        if (!is_int($start) || $start <= 0) {
            return;
        }

        // Min/Max globaux, overridables ponctuellement
        $min = $minSeconds ?? $this->defaultMinSeconds;
        $max = $maxSeconds ?? $this->defaultMaxSeconds;

        // Garde-fous
        if ($min < 0) {
            $min = 0;
        }
        if ($max <= 0) {
            $max = $this->defaultMaxSeconds > 0 ? $this->defaultMaxSeconds : 1800;
        }
        if ($max < $min) {
            $max = $min;
        }

        $elapsed = time() - $start;

        $minOkKey     = $this->getMinOkKey($formId);
        $minAlreadyOk = (bool) $this->session->get($minOkKey);

        if ($elapsed > $max) {
            throw new SuspiciousSubmissionException('max_delay_exceeded', [
                'form'    => $formId,
                'elapsed' => $elapsed,
                'min'     => $min,
                'max'     => $max,
            ]);
        }

        if (!$minAlreadyOk) {
            if ($elapsed < $min) {
                throw new SuspiciousSubmissionException('min_delay_not_met', [
                    'form'    => $formId,
                    'elapsed' => $elapsed,
                    'min'     => $min,
                    'max'     => $max,
                ]);
            }

            // Une première soumission humaine a respecté le délai minimal :
            // on assouplit les soumissions suivantes (correction d’erreurs, etc.).
            $this->session->set($minOkKey, 1);
        }
    }

    private function getKey(string $formId): string
    {
        return 'form_start_' . $formId;
    }

    private function getMinOkKey(string $formId): string
    {
        return 'form_min_ok_' . $formId;
    }
}
