<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Handles the database connection using PDO.
 *
 * This class is responsible for:
 * - Reading database configuration from environment variables.
 * - Establishing a secure PDO connection with error handling.
 * - Providing a method to access the active PDO instance.
 */
class Database
{
    private PDO $pdo;

    /**
     * Initializes the PDO connection using environment variables.
     *
     * Environment variables required:
     * - DB_NAME or DB_NAME_TEST (depending on the environment)
     * - DB_HOST
     * - DB_CHARSET (optional, defaults to utf8mb4)
     * - DB_USER
     * - DB_PASSWORD (optional, defaults to empty string)
     *
     * Throws RuntimeException if a required variable is missing or invalid.
     * Logs PDO errors using the Logger service.
     */
    public function __construct()
    {
        $env = $_ENV['APP_ENV'] ?? 'prod';

        $dbName     = $this->getRequiredEnvString($env === 'test' ? 'DB_NAME_TEST' : 'DB_NAME');
        $dbHost     = $this->getRequiredEnvString('DB_HOST');

        // --- PHPStan-safe handling ---
        $dbCharsetRaw = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        if (!is_string($dbCharsetRaw)) {
            throw new RuntimeException('DB_CHARSET must be a string.');
        }
        $dbCharset = $dbCharsetRaw;

        $dbUser     = $this->getRequiredEnvString('DB_USER');

        $dbPasswordRaw = $_ENV['DB_PASSWORD'] ?? '';
        if (!is_string($dbPasswordRaw)) {
            throw new RuntimeException('DB_PASSWORD must be a string.');
        }
        $dbPassword = $dbPasswordRaw;
        // --- End safe handling ---

        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";

        try {
            $this->pdo = new PDO($dsn, $dbUser, $dbPassword, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            ]);
        } catch (PDOException $e) {
            Logger::getLogger('db')->error('PDO error', [
                'dsn'     => $dsn,
                'env'     => $env,
                'message' => $e->getMessage()
            ]);
            throw new RuntimeException('Database connection failed.');
        }
    }

    /**
     * Returns the active PDO connection.
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Reads a required environment variable and ensures it is a non-empty string.
     *
     * @throws RuntimeException if the variable is missing, not a string, or empty.
     */
    private function getRequiredEnvString(string $key): string
    {
        if (!isset($_ENV[$key])) {
            throw new RuntimeException("Missing environment variable $key.");
        }

        $value = $_ENV[$key];
        if (!is_string($value)) {
            throw new RuntimeException("Environment variable $key must be a string.");
        }

        if (trim($value) === '') {
            throw new RuntimeException("Environment variable $key is empty.");
        }

        return $value;
    }
}
