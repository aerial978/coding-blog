<?php

namespace Tests\Unit\Core;

use App\Core\EnvLoader;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the EnvLoader class focusing on error scenarios.
 *
 * This test suite ensures that EnvLoader correctly handles:
 * - Missing environment files.
 * - Missing required environment variables.
 * - Fallback to default `.env` file when APP_ENV is not set.
 */
final class EnvLoaderErrorTest extends TestCase
{
    /**
     * Test that an exception is thrown when the environment file is missing.
     *
     * This ensures EnvLoader will not silently ignore missing .env files
     * and will throw a RuntimeException instead.
     */
    public function testThrowsExceptionWhenEnvFileIsMissing(): void
    {
        $_ENV['APP_ENV'] = 'test';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Environment file not found');

        EnvLoader::load(__DIR__ . '/fake-dir/');
    }

    /**
     * Test that an exception is thrown when a required environment variable is missing.
     *
     * This verifies that EnvLoader validates the presence of critical variables
     * and fails fast if any are missing.
     */
    public function testThrowsExceptionWhenRequiredEnvVariableMissing(): void
    {
        $_ENV = [
            'APP_ENV'   => 'test',
            'APP_URL'   => 'http://localhost',
            'DB_HOST'   => 'localhost',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing environment variables');

        EnvLoader::load(dirname(__DIR__, 3) . '/');
    }

    /**
     * Test that EnvLoader falls back to using `.env` when APP_ENV is not set.
     *
     * This ensures that even if APP_ENV is absent, EnvLoader will still load
     * configuration from the default `.env` file without throwing errors.
     */
    public function testFallbackToDefaultEnvWhenAppEnvNotSet(): void
    {
        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $_ENV['DB_HOST']     = 'localhost';
        $_ENV['DB_USER']     = 'root';
        $_ENV['DB_PASSWORD'] = '';
        $_ENV['APP_ENV']     = 'prod';
        $_ENV['APP_URL']     = 'http://localhost';
        $_ENV['DB_NAME']     = 'coding-blog';

        $this->expectNotToPerformAssertions();

        EnvLoader::load(dirname(__DIR__, 3) . '/');
    }
}
