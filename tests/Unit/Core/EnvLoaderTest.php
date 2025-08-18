<?php

namespace Tests\Unit\Core;

use App\Core\EnvLoader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Unit tests for the EnvLoader class.
 *
 * This test suite ensures that EnvLoader:
 * - Loads environment variables correctly.
 * - Falls back to 'prod' when APP_ENV is missing or invalid.
 * - Validates required environment variables properly.
 * - Throws exceptions when variables are missing, empty, or have invalid types.
 */
final class EnvLoaderTest extends TestCase
{
    /**
     * Test that environment variables are loaded and contain required keys.
     *
     * This ensures that after loading, essential variables like DB_HOST, DB_USER,
     * and DB_PASSWORD exist in the environment.
     */
    public function testEnvLoadsVariables(): void
    {
        $env = $_ENV['APP_ENV'] ?? null;
        $this->assertNotNull($env);
        $this->assertContains($env, ['prod', 'test']);
        $this->assertArrayHasKey('DB_HOST', $_ENV);
        $this->assertArrayHasKey('DB_USER', $_ENV);
        $this->assertArrayHasKey('DB_PASSWORD', $_ENV);
    }

    /**
     * Test that resolveAppEnv() falls back to 'prod' when APP_ENV is missing, empty, or null.
     */
    public function testResolveAppEnvFallbacksToProdIfMissingOrInvalid(): void
    {
        $backup = $_ENV['APP_ENV'] ?? null;

        unset($_ENV['APP_ENV']);
        $this->assertSame('prod', $this->invokeResolveAppEnv());

        $_ENV['APP_ENV'] = '';
        $this->assertSame('prod', $this->invokeResolveAppEnv());

        $_ENV['APP_ENV'] = null;
        $this->assertSame('prod', $this->invokeResolveAppEnv());

        if ($backup !== null) {
            $_ENV['APP_ENV'] = $backup;
        } else {
            unset($_ENV['APP_ENV']);
        }
    }

    /**
     * Test that validateRequiredVariables() fails if DB_PASSWORD is not a string.
     */
    public function testValidateRequiredVariablesFailsIfDbPasswordNotString(): void
    {
        $_ENV['DB_PASSWORD']  = ['not', 'a', 'string']; // force error
        $_ENV['APP_ENV']      = 'test';
        $_ENV['DB_HOST']      = 'localhost';
        $_ENV['DB_USER']      = 'root';
        $_ENV['DB_NAME_TEST'] = 'test_db';
        $_ENV['APP_URL']      = 'http://localhost';

        $reflection = new ReflectionClass(EnvLoader::class);
        $method = $reflection->getMethod('validateRequiredVariables');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB_PASSWORD/');
        $method->invoke(null, 'test');
    }

    /**
     * Test that validateRequiredVariables() fails if a variable is empty.
     */
    public function testValidateRequiredVariablesFailsIfVariableIsEmpty(): void
    {
        $_ENV['APP_ENV']      = 'test';
        $_ENV['DB_HOST']      = ' ';
        $_ENV['DB_USER']      = 'root';
        $_ENV['DB_PASSWORD']  = '';
        $_ENV['DB_NAME_TEST'] = '';
        $_ENV['APP_URL']      = 'http://localhost';

        $reflection = new ReflectionClass(EnvLoader::class);
        $method = $reflection->getMethod('validateRequiredVariables');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB_NAME_TEST/');
        $method->invoke(null, 'test');
    }

    /**
     * Utility method to call the private resolveAppEnv() method.
     *
     * @return string The resolved application environment.
     */
    private function invokeResolveAppEnv(): string
    {
        $reflection = new ReflectionClass(EnvLoader::class);
        $method = $reflection->getMethod('resolveAppEnv');
        $method->setAccessible(true);

        /** @var string $value */
        $value = $method->invoke(null);

        return $value;
    }

    /**
     * Test that resolveAppEnv() returns 'prod' when both $_ENV and getenv() are missing.
     */
    public function testResolveAppEnvReturnsProdWhenEnvAndGetenvMissing(): void
    {
        $backupEnv = $_ENV['APP_ENV'] ?? null;
        $backupGetenv = getenv('APP_ENV');

        unset($_ENV['APP_ENV']);
        putenv('APP_ENV');

        $result = $this->invokeResolveAppEnv();

        $this->assertSame('prod', $result);

        if ($backupEnv !== null) {
            $_ENV['APP_ENV'] = $backupEnv;
        }

        if ($backupGetenv !== false) {
            putenv("APP_ENV=$backupGetenv");
        }
    }

    public function testIsNonEmptyStringVarReturnsFalseWhenNotString(): void
    {
        $_ENV['APP_URL'] = 12345; // valeur non-string
        $method = new ReflectionMethod(\App\Core\EnvLoader::class, 'isNonEmptyStringVar');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'APP_URL');

        $this->assertFalse($result);
    }
}
