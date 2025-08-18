<?php

namespace Tests\Unit\Core;

use App\Core\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the Database class.
 *
 * This test suite verifies that the Database class can successfully
 * create a PDO connection and properly handles invalid or missing
 * environment variable configurations.
 */
final class DatabaseTest extends TestCase
{
    /** @var array<string, mixed> Stores the original environment variables for restoration after each test */
    private array $originalEnv;

    /**
     * Set up the test environment before each test.
     *
     * Saves the current environment variables and sets valid default values
     * to allow successful database connection tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = array_filter($_ENV, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);

        $_ENV['DB_NAME']      = 'coding-blog';
        $_ENV['DB_HOST']      = 'localhost';
        $_ENV['DB_USER']      = 'root';
        $_ENV['DB_PASSWORD']  = '';
        $_ENV['DB_CHARSET']   = 'utf8mb4';
        $_ENV['APP_ENV']      = 'test';
        $_ENV['DB_NAME_TEST'] = 'coding-blog_test';
    }

    /**
     * Restore the original environment variables after each test.
     */
    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
    }

    /**
     * Test that getConnection() returns a PDO instance.
     */
    public function testConnectionReturnsPdoInstance(): void
    {
        $pdo = (new Database())->getConnection();
        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    /**
     * Test that an invalid DB_CHARSET type throws an exception.
     */
    public function testThrowsWhenDbCharsetIsNotString(): void
    {
        $_ENV['DB_CHARSET'] = ['invalid'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_CHARSET must be a string.');
        new Database();
    }

    /**
     * Test that an invalid DB_PASSWORD type throws an exception.
     */
    public function testThrowsWhenDbPasswordIsNotString(): void
    {
        $_ENV['DB_PASSWORD'] = ['not_a_string'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_PASSWORD must be a string.');
        new Database();
    }

    /**
     * Test that an invalid environment variable type throws an exception.
     */
    public function testThrowsWhenEnvValueIsNotString(): void
    {
        $_ENV['DB_HOST'] = ['not_a_string'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Environment variable DB_HOST must be a string.');
        new Database();
    }

    /**
     * Test that an empty environment variable value throws an exception.
     */
    public function testThrowsWhenEnvValueIsEmptyString(): void
    {
        $_ENV['DB_NAME_TEST'] = '   ';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Environment variable DB_NAME_TEST is empty.');
        new Database();
    }
}
