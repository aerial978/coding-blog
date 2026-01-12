<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Contract\SessionInterface;
use App\Security\Contract\SubmissionDelayValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;

final class SubmissionDelayValidator implements SubmissionDelayValidatorInterface
{
    private SessionInterface $session;

    /**
     * Valeurs par défaut (si aucun réglage spécifique au formulaire).
     */
    private int $defaultMinSeconds;
    private int $defaultMaxSeconds;

    /**
     * Règles spécifiques par formulaire.
     *
     * @var array<string, array{min:int, max:int}>
     */
    private array $rules = [
        // exemple : inscription
        'register' => [
            'min' => 15,    // délai minimal en secondes
            'max' => 1800,  // délai maximal : 30 minutes
        ],
        // exemple : renvoi de confirmation
        'resend_confirm' => [
            'min' => 5,
            'max' => 900,   // 15 minutes
        ],
    ];

    public function __construct(
        SessionInterface $session,
        int $defaultMinSeconds = 5,
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
        $key   = $this->getKey($formId);
        $start = $this->session->get($key);

        // Si aucune trace du GET : on ne bloque pas (comportement neutre)
        if (!is_int($start)) {
            return;
        }

        $startInt = $start;
        if ($startInt <= 0) {
            return;
        }

        // Résolution des bornes min/max : priorité aux paramètres explicites,
        // sinon aux règles par formulaire, sinon aux valeurs par défaut.
        [$min, $max] = $this->resolveBounds($formId, $minSeconds, $maxSeconds);

        $elapsed = time() - $startInt;

        $minOkKey      = $this->getMinOkKey($formId);
        $minAlreadyOk  = (bool) $this->session->get($minOkKey);

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

    /**
     * Calcule les bornes min/max effectives pour un formulaire.
     *
     * @return array{0:int,1:int} [min, max]
     */
    private function resolveBounds(
        string $formId,
        ?int $minOverride,
        ?int $maxOverride
    ): array {
        $min = $this->defaultMinSeconds;
        $max = $this->defaultMaxSeconds;

        if (isset($this->rules[$formId])) {
            $min = $this->rules[$formId]['min'];
            $max = $this->rules[$formId]['max'];
        }

        if ($minOverride !== null) {
            $min = $minOverride;
        }
        if ($maxOverride !== null) {
            $max = $maxOverride;
        }

        return [$min, $max];
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
