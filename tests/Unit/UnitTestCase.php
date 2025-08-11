<?php

namespace Tests\Unit;

use App\Core\EnvLoader;
use PHPUnit\Framework\TestCase;

/**
 * Base class for unit tests.
 *
 * This abstract test case sets up the environment variables required
 * for running unit tests and ensures that the test database configuration
 * is loaded before each test.
 */
abstract class UnitTestCase extends TestCase
{
    /**
     * Set up the unit test environment.
     *
     * This method initializes the necessary environment variables
     * for the test environment and loads the configuration
     * from the `.env.test` (or equivalent) file using EnvLoader.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Define required environment variables for testing
        $_ENV['APP_ENV']      = 'test';
        $_ENV['DB_HOST']      = 'localhost';
        $_ENV['DB_USER']      = 'root';
        $_ENV['DB_PASSWORD']  = '';
        $_ENV['DB_NAME_TEST'] = 'coding-blog_test';
        $_ENV['APP_URL']      = 'http://localhost';

        // Ensure APP_ENV is set to 'test' before loading
        $_ENV['APP_ENV'] = 'test';

        // Load environment configuration for the test environment
        EnvLoader::load(dirname(__DIR__, 2) . '/');
    }
}
