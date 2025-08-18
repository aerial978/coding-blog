<?php

namespace Tests\Unit\Core;

use App\Core\AppConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the AppConfig class.
 *
 * This class verifies that environment configuration values
 * such as APP_ENV and APP_URL are correctly interpreted
 * by the AppConfig class methods.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class AppConfigTest extends TestCase
{
    /**
     * Set up environment variables before each test.
     *
     * This ensures that APP_URL and APP_ENV are defined
     * so the tests have a predictable environment.
     */
    protected function setUp(): void
    {
        $_ENV['APP_URL'] = 'http://localhost/coding-blog/';
        $_ENV['APP_ENV'] = 'local';
    }

    /**
     * Test the isLocal() and isProd() methods.
     *
     * Ensures that the environment is detected as "local"
     * and not as "production".
     */
    public function testIsLocal(): void
    {
        $this->assertTrue(AppConfig::isLocal());
        $this->assertFalse(AppConfig::isProd());
    }

    /**
     * Test the getAppUrl() method.
     *
     * Verifies that the returned application URL is formatted
     * without a trailing slash.
     */
    public function testGetAppUrl(): void
    {
        $this->assertEquals('http://localhost/coding-blog', AppConfig::getAppUrl());
    }
}
