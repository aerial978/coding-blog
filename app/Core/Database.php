<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private PDO $pdo;

    public function __construct()
    {
        $env = $this->resolveAppEnv();

        [$dbName, $dbHost, $dbCharset, $dbUser, $dbPassword] = $this->resolveConfig($env);

        $dsn       = $this->buildDsn($dbHost, $dbName, $dbCharset);
        $this->pdo = $this->createPdo($dsn, $dbUser, $dbPassword, $env);
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    // ---------- Helpers (extraction = complexité réduite) ----------

    private function resolveAppEnv(): string
    {
        $env = $_ENV['APP_ENV'] ?? 'prod';
        return is_string($env) && $env !== '' ? $env : 'prod';
    }

    /**
     * @return array{0:string,1:string,2:string,3:string,4:string}
     */
    private function resolveConfig(string $env): array
    {
        $dbName = $this->getRequiredEnvString($env === 'test' ? 'DB_NAME_TEST' : 'DB_NAME');
        $dbHost = $this->getRequiredEnvString('DB_HOST');

        $dbCharsetRaw = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        if (!is_string($dbCharsetRaw)) {
            throw new RuntimeException('DB_CHARSET must be a string.');
        }
        $dbCharset = $dbCharsetRaw;

        $dbUser = $this->getRequiredEnvString('DB_USER');

        $dbPasswordRaw = $_ENV['DB_PASSWORD'] ?? '';
        if (!is_string($dbPasswordRaw)) {
            throw new RuntimeException('DB_PASSWORD must be a string.');
        }
        $dbPassword = $dbPasswordRaw;

        return [$dbName, $dbHost, $dbCharset, $dbUser, $dbPassword];
    }

    private function buildDsn(string $host, string $dbName, string $charset): string
    {
        return "mysql:host={$host};dbname={$dbName};charset={$charset}";
    }

    private function createPdo(string $dsn, string $user, string $password, string $env): PDO
    {
        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            ]);
        } catch (PDOException $e) {
            Logger::getLogger('db')->error('PDO error', [
                'dsn'     => $dsn,
                'env'     => $env,
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Database connection failed.');
        }
    }

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
