<?php

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Utility class to simplify SQL queries execution.
 *
 * This class wraps a PDO instance and provides a single `request` method
 * that supports both prepared statements and direct queries.
 */
class SqlHelper
{
    /**
     * Active database connection instance.
     */
    protected PDO $databaseConnection;

    /**
     * Constructor.
     *
     * @param PDO $pdo The PDO instance representing the database connection.
     */
    public function __construct(PDO $pdo)
    {
        $this->databaseConnection = $pdo;
    }

    /**
     * Executes an SQL query, either prepared (with parameters) or direct.
     *
     * - If `$params` is provided, the SQL is prepared and executed with bound parameters.
     * - If `$params` is null, the SQL is executed directly.
     *
     * @param string $sql The SQL query string.
     * @param array<string, mixed>|null $params Optional parameters for prepared statements.
     * @return PDOStatement The resulting PDO statement after execution.
     *
     * @throws RuntimeException If the direct query fails.
     */
    public function request(string $sql, ?array $params = null): PDOStatement
    {
        if ($params !== null) {
            $statement = $this->databaseConnection->prepare($sql);
            $statement->execute($params);
            return $statement;
        }

        $statement = $this->databaseConnection->query($sql);
        if ($statement === false) {
            throw new RuntimeException("SQL query failed: $sql");
        }

        return $statement;
    }
}
