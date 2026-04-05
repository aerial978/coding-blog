<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Contract\SqlHelperInterface;
use PDO;
use PDOStatement;
use RuntimeException;

final class SqlHelper implements SqlHelperInterface
{
    /**
     * Active database connection instance.
     */
    protected PDO $databaseConnection;

    public function __construct(PDO $pdo)
    {
        $this->databaseConnection = $pdo;
    }

    /**
     * Executes an SQL query, either prepared (with parameters) or direct.
     *
     * @param array<string, mixed> $params
     *
     * @throws RuntimeException
     */
    public function request(string $sql, array $params = []): PDOStatement
    {
        if ($params !== []) {
            $statement = $this->databaseConnection->prepare($sql);

            if ($statement === false) {
                throw new RuntimeException("SQL prepare failed: $sql");
            }

            $executed = $statement->execute($params);

            if ($executed === false) {
                throw new RuntimeException("SQL execute failed: $sql");
            }

            return $statement;
        }

        $statement = $this->databaseConnection->query($sql);

        if ($statement === false) {
            throw new RuntimeException("SQL query failed: $sql");
        }

        return $statement;
    }

    public function lastInsertId(): int
    {
        return (int) $this->databaseConnection->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->databaseConnection->beginTransaction();
    }

    public function commit(): void
    {
        $this->databaseConnection->commit();
    }

    public function rollBack(): void
    {
        if ($this->databaseConnection->inTransaction()) {
            $this->databaseConnection->rollBack();
        }
    }
}
