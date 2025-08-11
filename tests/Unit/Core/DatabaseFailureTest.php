<?php

namespace Tests\Unit\Core;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Database failure scenarios.
 *
 * This test suite ensures that the Database class throws the expected
 * RuntimeException when mandatory environment variables are missing
 * or database credentials are invalid.
 */
final class DatabaseFailureTest extends TestCase
{
    /** @var array<mixed> Stores the original environment variables for restoration after each test */
    private array $originalEnv;

    /**
     * Save the current environment variables before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var array<mixed> $envCopy */
        $envCopy           = $_ENV;
        $this->originalEnv = $envCopy;
    }

    /**
     * Restore the original environment variables after each test.
     */
    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
    }

    /**
     * Test that a missing DB_NAME_TEST environment variable throws an exception.
     *
     * This ensures that the Database class validates the presence of required
     * environment variables before attempting to create a connection.
     */
    public function testMissingDatabaseNameThrowsException(): void
    {
        $_ENV['APP_ENV']     = 'test';
        $_ENV['DB_HOST']     = 'localhost';
        $_ENV['DB_USER']     = 'root';
        $_ENV['DB_PASSWORD'] = '';
        unset($_ENV['DB_NAME_TEST']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing environment variable DB_NAME_TEST.');

        $pdo = (new Database())->getConnection();
        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    /**
     * Test that invalid database credentials throw an exception.
     *
     * This ensures that the Database class properly handles failed
     * authentication when connecting to the database.
     */
    public function testInvalidCredentialsThrowsException(): void
    {
        $_ENV['APP_ENV']      = 'test';
        $_ENV['DB_HOST']      = 'localhost';
        $_ENV['DB_NAME_TEST'] = 'coding-blog_test';
        $_ENV['DB_USER']      = 'invalid user';
        $_ENV['DB_PASSWORD']  = 'wrong password';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed.');

        $pdo = (new Database())->getConnection();
        $this->assertInstanceOf(\PDO::class, $pdo);
    }
}
