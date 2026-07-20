<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Contract\SqlHelperInterface;
use App\Model\Contract\EmailEventModelInterface;
use DateInterval;
use DateTimeImmutable;

final class EmailEventModel implements EmailEventModelInterface
{
    protected string $table = 'email_events';

    /**
     * Constructor.
     *
     * Initializes the model with a SqlHelper instance for performing
     * parameterized SQL queries safely and consistently.
     *
     * @param SqlHelperInterface $sqlHelper
     *     Helper utility for preparing and executing SQL queries.
     */
    public function __construct(
        private SqlHelperInterface $sqlHelper
    ) {
    }

    /**
     * Enregistre un événement d’envoi d’email (quota métier).
     */
    public function recordEvent(string $email, string $type, ?int $userId, string $ip, string $userAgent): bool
    {

        $sql = "INSERT INTO {$this->table} (email, type, user_id, ip, user_agent, created_at)
            VALUES (:email, :type, :user_id, :ip, :user_agent, NOW())";

        $params = [
            ':email'      => $email,
            ':type'       => $type,
            ':user_id'    => $userId,
            ':ip'         => $ip,
            ':user_agent' => $userAgent
        ];

        $st = $this->sqlHelper->request($sql, $params);

        return $st->rowCount() === 1;
    }

    /**
     * Compte le nombre d’événements d’envoi d’email dans une fenêtre donnée.
     */
    public function countEvents(string $email, string $type, int $secondsWindow): int
    {
        $cutoff = (new DateTimeImmutable('now'))
            ->sub(new DateInterval('PT' . $secondsWindow . 'S'))
            ->format('Y-m-d H:i:s');

        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE email = :email AND type  = :type AND created_at >= :cutoff";

        $st = $this->sqlHelper->request($sql, [
            ':email'  => $email,
            ':type'   => $type,
            ':cutoff' => $cutoff,
        ]);

        return (int) $st->fetchColumn();
    }

    /**
     * Supprime les vieux événements (cleanup facultatif)
     */
    public function deleteOlderThan(int $days): int
    {
        $sql = "DELETE FROM {$this->table} WHERE created_at < (NOW() - INTERVAL :days DAY)";

        $st = $this->sqlHelper->request($sql, [
            ':days' => $days
        ]);

        return $st->rowCount();
    }
}
