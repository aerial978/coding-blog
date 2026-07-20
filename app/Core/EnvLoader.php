<?php

namespace App\Core;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Loads and validates environment variables from .env files.
 *
 * Responsibilities:
 *  - Detect current environment (APP_ENV).
 *  - Select the correct .env file (.env or .env.test).
 *  - Load variables into $_ENV using vlucas/phpdotenv.
 *  - Validate the presence and type of required variables.
 */
class EnvLoader
{
    /**
     * Entry point to load environment variables.
     *
     * @param string|null $path Base path where the .env files live. Defaults to project root.
     * @throws RuntimeException If the .env file is missing or required variables are invalid.
     */
    public static function load(?string $path = null): void
    {
        $path ??= dirname(__DIR__, 2) . '/';

        $appEnv = self::resolveAppEnv();

        $dotenvFile = self::resolveDotenvFile($appEnv);
        self::loadEnvFile($path, $dotenvFile);

        self::validateRequiredVariables($appEnv);
    }

    /**
     * Determine the current application environment.
     * Falls back to "prod" when not set or invalid.
     */
    private static function resolveAppEnv(): string
    {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV'); // string|false|null

        if (!is_string($env) || trim($env) === '') {
            return 'prod';
        }

        return $env;
    }

    /**
     * Choose which .env file to load depending on the environment.
     */
    private static function resolveDotenvFile(string $appEnv): string
    {
        return $appEnv === 'test' ? '.env.test' : '.env';
    }

    /**
     * Load the given .env file into the environment.
     *
     * @throws RuntimeException When the file does not exist.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private static function loadEnvFile(string $path, string $file): void
    {
        $filePath = $path . $file;

        if (!file_exists($filePath)) {
            throw new RuntimeException("Environment file not found: $file");
        }

        // Static factory is the intended usage pattern for vlucas/phpdotenv
        $dotenv = Dotenv::createImmutable($path, $file);
        $dotenv->load();
    }

    /**
     * Validate required environment variables for the given environment.
     *
     * Rules:
     *  - DB_PASSWORD may be empty, but must exist and be a string.
     *  - All other required variables must be non-empty strings.
     *
     * @throws RuntimeException When one or more variables are missing/invalid.
     */
    private static function validateRequiredVariables(string $appEnv): void
    {
        $requiredBase = [
            'DB_HOST',
            'DB_USER',
            'DB_PASSWORD', // may be empty but must exist and be a string
            'APP_ENV',
            'APP_URL',
        ];

        $dbNameKey = $appEnv === 'test' ? 'DB_NAME_TEST' : 'DB_NAME';
        $required  = [...$requiredBase, $dbNameKey];

        $missing = [];

        foreach ($required as $key) {
            if ($key === 'DB_PASSWORD') {
                if (!self::isStringVar($key)) {
                    $missing[] = $key;
                }
                continue;
            }

            if (!self::isNonEmptyStringVar($key)) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            $list = implode(', ', $missing);
            throw new RuntimeException("Missing environment variables: $list");
        }
    }

    /**
     * Helper: variable exists and is a string (can be empty).
     */
    private static function isStringVar(string $key): bool
    {
        if (!array_key_exists($key, $_ENV)) {
            return false;
        }

        return is_string($_ENV[$key]);
    }

    /**
     * Helper: variable exists and is a non-empty string.
     */
    private static function isNonEmptyStringVar(string $key): bool
    {
        if (!array_key_exists($key, $_ENV)) {
            return false;
        }

        $value = $_ENV[$key];

        if (!is_string($value)) {
            return false;
        }

        return trim($value) !== '';
    }
}
