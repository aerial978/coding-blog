<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Logger;
use App\Model\Contract\EmailEventModelInterface;
use App\Security\Contract\EmailQuotaServiceInterface;

final class EmailQuotaService implements EmailQuotaServiceInterface
{
    public const TYPE_CONFIRM_RESEND = 'confirm_resend';
    // Vous pourrez ajouter: const TYPE_PASSWORD_RESET = 'password_reset', etc.

    /**
     * Règles de quotas métier par type.
     * Exemple: 3 tentatives / heure, 10 / jour.
     *
     * @var array<string, array{per_hour:int, per_day:int}>
     */
    private const LIMITS = [
        self::TYPE_CONFIRM_RESEND => [
            'per_hour' => 3,
            'per_day'  => 10,
        ],
    ];

    public function __construct(private EmailEventModelInterface $emailEventModel)
    {
        Logger::getLogger('auth')->info('quota_limits_loaded', [
            'limits' => self::LIMITS
        ]);
    }

    /**
     * Vérifie si on peut encore envoyer un e-mail de ce type pour cette adresse.
     *
     * @return array{allowed:bool, reason:?string}
     *   reason peut valoir 'hour_quota_exceeded' ou 'day_quota_exceeded' par ex.
     */
    public function checkQuota(string $type, string $email): array
    {
        $limits = self::LIMITS[$type] ?? null;

        if ($limits === null) {
            Logger::getLogger('auth')->info('quota_skip_no_rules', [
            'email' => $email,
            'type'  => $type
            ]);

            // Pas de règle définie → tout est permis
            return ['allowed' => true, 'reason' => null];
        }

        // Fenêtres en secondes
        $hourWindow = 3600;   // 1 heure
        $dayWindow  = 86400;  // 24 heures

        // ----- COMPTAGES -----
        $countHour = $this->emailEventModel->countEvents($email, $type, $hourWindow);
        $countDay  = $this->emailEventModel->countEvents($email, $type, $dayWindow);

        // Quota horaire
        if ($countHour >= $limits['per_hour']) {
            Logger::getLogger('auth')->warning('quota_hour_exceeded', [
                'email'      => $email,
                'type'       => $type,
                'countHour'  => $countHour,
                'limitHour'  => $limits['per_hour'],
            ]);

            return ['allowed' => false, 'reason' => 'hour_quota_exceeded'];
        }

        if ($countDay >= $limits['per_day']) {
            Logger::getLogger('auth')->warning('quota_day_exceeded', [
                'email'     => $email,
                'type'      => $type,
                'countDay'  => $countDay,
                'limitDay'  => $limits['per_day'],
            ]);

            return ['allowed' => false, 'reason' => 'day_quota_exceeded'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Enregistre un événement d’envoi d’e-mail après un envoi réussi.
     */
    public function recordEvent(
        string $email,
        string $type,
        ?int $userId,
        ?string $ip,
        ?string $userAgent
    ): bool {
        $safeIp        = $ip        ?? '0.0.0.0';
        $safeUserAgent = $userAgent ?? 'unknown';

        return $this->emailEventModel->recordEvent(
            $email,
            $type,
            $userId,
            $safeIp,
            $safeUserAgent
        );
    }

    /**
     * Nettoyage optionnel des vieux événements (pour une future tâche CRON).
     */
    public function cleanup(int $days = 30): int
    {
        return $this->emailEventModel->deleteOlderThan($days);
    }
}
