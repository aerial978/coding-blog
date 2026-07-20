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
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['APP_ENV']      = 'test';
        $_ENV['DB_HOST']      = 'localhost';
        $_ENV['DB_USER']      = 'root';
        $_ENV['DB_PASSWORD']  = '';
        $_ENV['DB_NAME_TEST'] = 'coding-blog_test';
        $_ENV['APP_URL']      = 'http://localhost';

        $_ENV['APP_ENV'] = 'test';

        EnvLoader::load(dirname(__DIR__, 2) . '/');
    }
}
