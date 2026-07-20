<?php

namespace App\Security;

use App\Security\Contract\HoneypotValidatorInterface;
use App\Security\Exception\SuspiciousSubmissionException;

/**
 * Détecte les soumissions suspectes via un champ "piège" (honeypot).
 * - Le champ est rendu en <input type="text"> masqué côté vue.
 * - Si le champ est non vide à la soumission, on considère la requête comme bot.
 */
final class HoneypotValidator implements HoneypotValidatorInterface
{
    /** @var string */
    private $fieldName;

    public function __construct(string $fieldName = 'fax')
    {
        $this->fieldName = $fieldName;
    }

    /**
     * @param array<string,mixed> $postData
     * @throws SuspiciousSubmissionException
     */
    public function assertClean(array $postData): void
    {
        $raw   = $postData[$this->fieldName] ?? null;
        $value = '';

        if (is_string($raw)) {
            $value = trim($raw);
        }

        if (is_int($raw) || is_float($raw) || $raw instanceof \Stringable) {
            $value = trim((string) $raw);
        }

        if ($value !== '') {
            // aucune fuite d’info : exception neutre, le contrôleur loguera
            throw new SuspiciousSubmissionException(
                'honeypot_triggered',
                ['field' => $this->fieldName]
            );
        }
    }

    public function fieldName(): string
    {
        return $this->fieldName;
    }
}
