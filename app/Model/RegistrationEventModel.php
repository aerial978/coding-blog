<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Contract\RegistrationEventModelInterface;
use DateInterval;
use DateTimeImmutable;

final class RegistrationEventModel implements RegistrationEventModelInterface
{
    protected string $table = 'registration_events';

    /**
     * Constructor.
     *
     * Initializes the model with a SqlHelper instance for
     * executing database queries.
     *
     * @param SqlHelperInterface $sqlHelper
     *     Helper class for preparing, executing, and managing SQL statements.
     */
    public function __construct(
        private SqlHelperInterface $sqlHelper
    ) {
    }

    /**
     * Enregistre un événement lié à l'inscription.
     */
    public function recordEvent(
        string $email,
        string $type,
        ?int $userId,
        string $ip,
        string $userAgent
    ): bool {
        $sql = "INSERT INTO {$this->table} 
                (email, type, user_id, ip, user_agent, created_at)
                VALUES (:email, :type, :user_id, :ip, :user_agent, NOW())";

        $st = $this->sqlHelper->request($sql, [
            ':email'      => $email,
            ':type'       => $type,
            ':user_id'    => $userId,
            ':ip'         => $ip,
            ':user_agent' => $userAgent,
        ]);

        return $st->rowCount() === 1;
    }

    /**
     * Compte les inscriptions depuis une certaine fenêtre en secondes.
     */
    public function countEvents(string $ip, int $secondsWindow): int
    {
        $cutoff = (new DateTimeImmutable('now'))
            ->sub(new DateInterval('PT' . $secondsWindow . 'S'))
            ->format('Y-m-d H:i:s');

        $sql = "SELECT COUNT(*) AS c
                FROM {$this->table}
                WHERE ip = :ip
                  AND created_at >= :cutoff";

        $st = $this->sqlHelper->request($sql, [
            ':ip'     => $ip,
            ':cutoff' => $cutoff,
        ]);

        return (int) $st->fetchColumn();
    }

    /**
     * Nettoyage facultatif (CRON futur).
     */
    public function deleteOlderThan(int $days): int
    {
        $sql = "DELETE FROM {$this->table}
                WHERE created_at < (NOW() - INTERVAL :days DAY)";

        $st = $this->sqlHelper->request($sql, [
            ':days' => $days,
        ]);

        return $st->rowCount();
    }
}
