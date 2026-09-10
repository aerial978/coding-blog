<?php

declare(strict_types=1);

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

    public function testIsTest(): void
    {
        $_ENV['APP_ENV'] = 'test';

        $this->assertTrue(AppConfig::isTest());
        $this->assertFalse(AppConfig::isLocal());
        $this->assertFalse(AppConfig::isProd());
    }

    public function testGetBasePathReturnsLocalSubdirectory(): void
    {
        $_ENV['APP_URL'] = 'http://localhost/coding-blog/';

        $this->assertSame('/coding-blog', AppConfig::getBasePath());
    }

    public function testGetBasePathReturnsEmptyStringForRootUrl(): void
    {
        $_ENV['APP_URL'] = 'https://coding-blog.example.com/';

        $this->assertSame('', AppConfig::getBasePath());
    }

    public function testGetBasePathSupportsNestedPath(): void
    {
        $_ENV['APP_URL'] = 'https://example.com/apps/coding-blog/';

        $this->assertSame('/apps/coding-blog', AppConfig::getBasePath());
    }

    public function testGetPathPrefixesLocalBasePath(): void
    {
        $_ENV['APP_URL'] = 'http://localhost/coding-blog/';

        $this->assertSame('/coding-blog/login', AppConfig::getPath('/login'));
    }

    public function testGetPathReturnsRootPathInProduction(): void
    {
        $_ENV['APP_URL'] = 'https://coding-blog.example.com/';

        $this->assertSame('/login', AppConfig::getPath('/login'));
    }

    public function testGetPathReturnsApplicationRoot(): void
    {
        $_ENV['APP_URL'] = 'http://localhost/coding-blog/';

        $this->assertSame('/coding-blog/', AppConfig::getPath('/'));
    }
}
