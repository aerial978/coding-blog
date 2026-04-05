<?php

declare(strict_types=1);

namespace App\Core\Contract;

use PDOStatement;

interface SqlHelperInterface
{
    /**
     * Exécute une requête SQL préparée avec paramètres.
     *
     * @param array<string, mixed> $params
     */
    public function request(string $sql, array $params = []): PDOStatement;

    public function lastInsertId(): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;
}
