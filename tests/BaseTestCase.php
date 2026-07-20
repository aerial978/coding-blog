<?php

namespace Tests;

use App\Core\EnvLoader;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for all PHPUnit tests.
 *
 * This abstract class sets up the default environment configuration
 * required for running tests and ensures that the `.env.test` file
 * is loaded before each test. All other test cases can extend this
 * class to inherit the environment setup.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Set up the base test environment.
     *
     * This method initializes essential environment variables for testing,
     * including database connection parameters, and then loads the
     * corresponding environment configuration file using EnvLoader.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Define required environment variables for the test environment
        $_ENV['APP_ENV']      = 'test';
        $_ENV['DB_HOST']      = 'localhost';
        $_ENV['DB_USER']      = 'root';
        $_ENV['DB_PASSWORD']  = '';
        $_ENV['DB_NAME_TEST'] = 'coding-blog_test';
        $_ENV['DB_CHARSET']   = 'utf8mb4';

        // Load environment configuration for the test environment
        EnvLoader::load(dirname(__DIR__, 2) . '/');
    }
}
