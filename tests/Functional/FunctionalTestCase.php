<?php

namespace Tests\Functional;

use App\Core\EnvLoader;
use PHPUnit\Framework\TestCase;

/**
 * Base class for functional tests.
 *
 * This abstract class provides a common setup for all functional tests,
 * ensuring that environment variables are loaded before each test
 * through the EnvLoader. Any functional test class should extend this
 * to guarantee a properly configured testing environment.
 */
abstract class FunctionalTestCase extends TestCase
{
    /**
     * Set up the functional test environment.
     *
     * This method loads the environment variables before each test
     * by using the EnvLoader. It ensures that the test environment
     * is properly configured before running any functional test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        EnvLoader::load();
    }
}
