<?php

namespace Tests\Unit\Core;

use App\Core\AppConfig;
use App\Core\Container;
use App\Core\ControllerFactoryInterface;
use App\Core\DefaultControllerFactory;
use App\Core\SqlHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the Container class.
 *
 * This test suite verifies that the service container correctly
 * instantiates and returns core application services such as
 * configuration, database connections, loggers, and factories.
 */
final class ContainerTest extends TestCase
{
    /** @var array<mixed> Stores the original environment variables for restoration after each test */
    private array $originalEnv;

    /**
     * Set up a test environment before each test.
     *
     * This method stores the current environment variables and
     * overrides them with values needed for the Container to function in tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = $_ENV;

        $_ENV['APP_ENV']      = 'test';
        $_ENV['DB_HOST']      = 'localhost';
        $_ENV['DB_USER']      = 'root';
        $_ENV['DB_PASSWORD']  = '';
        $_ENV['DB_CHARSET']   = 'utf8mb4';
        $_ENV['DB_NAME']      = 'coding-blog';
        $_ENV['DB_NAME_TEST'] = 'coding-blog_test';
    }

    /**
     * Restore the original environment variables after each test.
     */
    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
        parent::tearDown();
    }

    /**
     * Test that the container returns an AppConfig instance.
     */
    public function testGetAppConfigReturnsAppConfigInstance(): void
    {
        $container = new Container();
        $this->assertInstanceOf(AppConfig::class, $container->getAppConfig());
    }

    /**
     * Test that the container returns a DefaultControllerFactory instance
     * implementing the ControllerFactoryInterface.
     */
    public function testGetControllerFactoryReturnsDefaultFactory(): void
    {
        $container = new Container();
        $factory   = $container->getControllerFactory();
        $this->assertInstanceOf(ControllerFactoryInterface::class, $factory);
        $this->assertInstanceOf(DefaultControllerFactory::class, $factory);
    }

    /**
     * Test that the container returns a valid PDO instance.
     */
    public function testGetPdoReturnsPdo(): void
    {
        $container = new Container();
        $this->assertInstanceOf(\PDO::class, $container->getPdo());
    }

    /**
     * Test that the container returns a SqlHelper instance.
     */
    public function testGetSqlHelperReturnsSqlHelper(): void
    {
        $container = new Container();
        $this->assertInstanceOf(SqlHelper::class, $container->getSqlHelper());
    }

    /**
     * Test that the container returns a PSR-3 compliant LoggerInterface instance.
     */
    public function testGetLoggerReturnsPsrLogger(): void
    {
        $container = new Container();
        $this->assertInstanceOf(LoggerInterface::class, $container->getLogger('test'));
    }

    /**
     * Test that the container returns a UserModel instance.
     */
    public function testGetUserModelReturnsUserModel(): void
    {
        $container = new Container();
        $this->assertInstanceOf(\App\Model\UserModel::class, $container->getUserModel());
    }
}
