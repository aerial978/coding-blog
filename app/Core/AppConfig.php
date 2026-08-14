<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Provides access to core application configuration values.
 */
class AppConfig
{
    private const ENV_LOCAL       = 'local';
    private const ENV_TEST        = 'test';
    private const ENV_PRODUCTION  = 'production';
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Returns the validated application base URL without a trailing slash.
     *
     * @throws RuntimeException If APP_URL is missing, empty or invalid.
     */
    public static function getAppUrl(): string
    {
        $url = $_ENV['APP_URL'] ?? null;

        if (!is_string($url)) {
            throw new RuntimeException(
                'APP_URL must be defined as a string.'
            );
        }

        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException(
                'APP_URL must not be empty.'
            );
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException(
                'APP_URL must be a valid URL.'
            );
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!is_string($scheme) || !in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new RuntimeException(
                'APP_URL must use the http or https scheme.'
            );
        }

        return rtrim($url, '/');
    }

    /**
     * Returns the validated application environment.
     *
     * @return string One of: local, test or production.
     *
     * @throws RuntimeException If APP_ENV is missing, invalid or unsupported.
     */
    public static function getEnvironment(): string
    {
        $environment = $_ENV['APP_ENV'] ?? null;

        if (!is_string($environment)) {
            throw new RuntimeException(
                'APP_ENV must be defined as a string.'
            );
        }

        $environment = trim($environment);

        if ($environment === '') {
            throw new RuntimeException(
                'APP_ENV must not be empty.'
            );
        }

        $allowedEnvironments = [
            self::ENV_LOCAL,
            self::ENV_TEST,
            self::ENV_PRODUCTION,
        ];

        if (!in_array($environment, $allowedEnvironments, true)) {
            throw new RuntimeException(
                sprintf(
                    'Unsupported APP_ENV value "%s". Allowed values: %s.',
                    $environment,
                    implode(', ', $allowedEnvironments)
                )
            );
        }

        return $environment;
    }

    /**
     * Checks if the current environment is production.
     */
    public static function isProd(): bool
    {
        return self::getEnvironment() === self::ENV_PRODUCTION;
    }

    /**
     * Checks if the current environment is local.
     */
    public static function isLocal(): bool
    {
        return self::getEnvironment() === self::ENV_LOCAL;
    }

    /**
     * Checks if the current environment is test.
     */
    public static function isTest(): bool
    {
        return self::getEnvironment() === self::ENV_TEST;
    }
}
