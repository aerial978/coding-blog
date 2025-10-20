<?php

namespace Tests\Unit\Core;

use App\Core\Logger;
use Monolog\Logger as MonoLogger;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Logger class.
 *
 * This test suite ensures that:
 * - A logger instance is properly created with a given channel name.
 * - The log directory is automatically created if it does not exist.
 * - Temporary log files and directories are cleaned up after tests.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class LoggerTest extends TestCase
{
    /**
     * Custom log channel name used for testing.
     *
     * @var string
     */
    private string $customChannel = 'test_channel';

    /**
     * Tear down the test environment.
     *
     * Cleans up any log files and directories created during tests
     * to ensure no leftover artifacts remain in the filesystem.
     */
    protected function tearDown(): void
    {
        $logDir = dirname(__DIR__, 3) . '/Logs/' . $this->customChannel;

        if (is_dir($logDir)) {
            $logFiles = glob("$logDir/*.log");
            if (is_array($logFiles)) {
                array_map('unlink', $logFiles);
            }
            rmdir($logDir);
        }

        parent::tearDown();
    }

    /**
     * Test that the logger creates the log directory if it does not already exist.
     *
     * This ensures that Logger::getLogger() will create a valid directory
     * for storing log files, and that it returns a Monolog Logger instance.
     */
    public function testLoggerCreatesLogDirectoryIfNotExists(): void
    {
        $logger = Logger::getLogger($this->customChannel);

        // Ensure the returned object is a valid Monolog Logger instance
        $this->assertInstanceOf(MonoLogger::class, $logger);

        // Verify that the expected log directory now exists
        $expectedDir = dirname(__DIR__, 3) . '/Logs/' . $this->customChannel;
        $this->assertDirectoryExists($expectedDir);
    }
}
