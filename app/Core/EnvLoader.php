<?php

namespace App\Core;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Loads and validates environment variables from .env files.
 *
 * This class determines the current application environment (APP_ENV),
 * loads the corresponding .env file, and ensures all required variables are set.
 */
class EnvLoader
{
    /**
     * Loads environment variables from the appropriate .env file.
     *
     * @param string|null $path Optional base path to look for the .env file. Defaults to project root.
     * @throws RuntimeException If the .env file is missing or required variables are not set.
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
     * Determines the current application environment from APP_ENV.
     * Defaults to 'prod' if APP_ENV is not set or invalid.
     *
     * @return string The environment name (e.g., 'prod', 'local', 'test').
     */
    private static function resolveAppEnv(): string
    {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV');

        if (!is_string($env) || trim($env) === '') {
            return 'prod';
        }

        return $env;
    }

    /**
     * Resolves which .env file to load based on the application environment.
     *
     * @param string $appEnv The current environment.
     * @return string The .env filename.
     */
    private static function resolveDotenvFile(string $appEnv): string
    {
        return match ($appEnv) {
            'test'  => '.env.test',
            default => '.env',
        };
    }

    /**
     * Loads the specified .env file into the environment.
     *
     * @param string $path The directory where the .env file is located.
     * @param string $file The name of the .env file.
     * @throws RuntimeException If the file does not exist.
     *
     * @SuppressWarnings("StaticAccess") // Required for Dotenv::createImmutable()
     */
    private static function loadEnvFile(string $path, string $file): void
    {
        $filePath = $path . $file;

        if (!file_exists($filePath)) {
            throw new RuntimeException("Environment file not found: $file");
        }

        $dotenv = Dotenv::createImmutable($path, $file);
        $dotenv->load();
    }

    /**
     * Validates that all required environment variables are present and valid.
     *
     * @param string $appEnv The current environment (used to check DB_NAME vs DB_NAME_TEST).
     * @throws RuntimeException If any required variables are missing or invalid.
     */
    private static function validateRequiredVariables(string $appEnv): void
    {
        $required = [
            'DB_HOST',
            'DB_USER',
            'DB_PASSWORD',  // May be empty but must exist
            'APP_ENV',
            'APP_URL',
            $appEnv === 'test' ? 'DB_NAME_TEST' : 'DB_NAME',
        ];

        $missing = [];

        foreach ($required as $key) {
            if (!array_key_exists($key, $_ENV)) {
                $missing[] = $key;
                continue;
            }

            $value = $_ENV[$key];

            // DB_PASSWORD can be empty, but must be a string
            if ($key === 'DB_PASSWORD') {
                if (!is_string($value)) {
                    $missing[] = $key;
                }
                continue;
            }

            // All others must be non-empty strings
            if (!is_string($value) || trim($value) === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            $list = implode(', ', $missing);
            throw new RuntimeException("Missing environment variables: $list");
        }
    }
}
