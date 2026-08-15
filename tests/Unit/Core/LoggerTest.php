<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Logger;
use Monolog\Logger as MonoLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the Logger class.
 *
 * This test suite ensures that:
 * - A logger instance is properly created with a valid channel name.
 * - The log directory is automatically created if it does not exist.
 * - Invalid or unsafe channel names are rejected.
 * - Temporary log files and directories are cleaned up after tests.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class LoggerTest extends TestCase
{
    /**
     * Custom log channel name used for testing.
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
                foreach ($logFiles as $logFile) {
                    @unlink($logFile);
                }
            }

            @rmdir($logDir);
        }

        Logger::reset();

        parent::tearDown();
    }

    /**
     * Test that the logger creates the log directory if it does not already exist.
     */
    public function testLoggerCreatesLogDirectoryIfNotExists(): void
    {
        $logger = Logger::getLogger($this->customChannel);

        self::assertInstanceOf(MonoLogger::class, $logger);

        $expectedDir = dirname(__DIR__, 3) . '/Logs/' . $this->customChannel;

        self::assertDirectoryExists($expectedDir);
    }

    /**
     * Test that a valid channel containing digits, underscores and hyphens
     * is accepted.
     */
    public function testLoggerAcceptsValidChannelName(): void
    {
        $this->customChannel = 'test_channel-123';

        $logger = Logger::getLogger($this->customChannel);

        self::assertInstanceOf(MonoLogger::class, $logger);

        $expectedDir = dirname(__DIR__, 3) . '/Logs/' . $this->customChannel;

        self::assertDirectoryExists($expectedDir);
    }

    /**
     * Test that an empty channel name is rejected.
     */
    public function testLoggerRejectsEmptyChannelName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Log channel must not be empty.');

        Logger::getLogger('');
    }

    /**
     * Test that a channel containing uppercase letters is rejected.
     */
    public function testLoggerRejectsUppercaseChannelName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid log channel');

        Logger::getLogger('Auth');
    }

    /**
     * Test that a channel starting with a digit is rejected.
     */
    public function testLoggerRejectsChannelStartingWithDigit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid log channel');

        Logger::getLogger('123auth');
    }

    /**
     * Test that a channel containing spaces is rejected.
     */
    public function testLoggerRejectsChannelContainingSpaces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid log channel');

        Logger::getLogger('auth log');
    }

    /**
     * Test that surrounding whitespace is rejected rather than normalized.
     */
    public function testLoggerRejectsChannelWithSurroundingWhitespace(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid log channel');

        Logger::getLogger(' auth ');
    }

    /**
     * Test that path traversal cannot be used as a channel name.
     */
    public function testLoggerRejectsPathTraversalChannel(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid log channel');

        Logger::getLogger('../auth');
    }
}
