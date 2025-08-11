<?php

namespace App\Core;

/**
 * Provides access to core application configuration values.
 */
class AppConfig
{
    /**
     * Returns the base application URL from environment variables.
     * Falls back to "http://localhost" if not set.
     *
     * @return string The application base URL without trailing slash.
     */
    public static function getAppUrl(): string
    {
        $url = $_ENV['APP_URL'] ?? null;
        return rtrim(is_string($url) ? $url : 'http://localhost', '/');
    }

    /**
     * Checks if the current environment is production.
     *
     * @return bool True if APP_ENV is set to "production".
     */
    public static function isProd(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'local') === 'production';
    }

    /**
     * Checks if the current environment is local.
     *
     * @return bool True if APP_ENV is set to "local".
     */
    public static function isLocal(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'local') === 'local';
    }
}
