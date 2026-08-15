<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger as MonoLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Logger factory and manager for the application.
 *
 * This class provides PSR-3 compatible loggers (via Monolog) and ensures
 * that each logging channel has its own instance and file.
 */
class Logger
{
    /**
     * Pattern allowed for logging channel names.
     *
     * A channel must:
     * - start with a lowercase letter;
     * - contain only lowercase letters, digits, underscores or hyphens.
     */
    private const CHANNEL_PATTERN = '/^[a-z][a-z0-9_-]*$/';

    /**
     * Stores logger instances by channel name.
     *
     * @var array<string, LoggerInterface>
     */
    private static array $instances = [];

    /**
     * Returns a PSR-3 compliant logger instance for the specified channel.
     *
     * If the logger for the given channel doesn't exist yet, it is created
     * with a rotating file handler (keeps logs for 14 days by default).
     *
     * @param string $channel The logging channel name.
     * @return LoggerInterface A PSR-3 logger instance.
     *
     * @throws RuntimeException If the channel name is invalid.
     */
    public static function getLogger(string $channel = 'app'): LoggerInterface
    {
        $channel = self::validateChannel($channel);

        if (!isset(self::$instances[$channel])) {
            $logger = new MonoLogger($channel);

            $baseLogDir    = dirname(__DIR__, 2) . '/Logs';
            $channelLogDir = $baseLogDir . '/' . $channel;

            if (!is_dir($channelLogDir)) {
                if (!mkdir($channelLogDir, 0755, true) && !is_dir($channelLogDir)) {
                    throw new RuntimeException(
                        sprintf(
                            'Unable to create log directory "%s".',
                            $channelLogDir
                        )
                    );
                }
            }

            $logFile = $channelLogDir . '/' . $channel . '.log';

            $logLevel = self::resolveLogLevel();

            $handler = new RotatingFileHandler($logFile, 14, $logLevel);

            // Formatter : supprime %context% et %extra% quand ils sont vides
            $format    = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
            $date      = 'c'; // ISO8601 (ex : 2025-08-23T14:33:12+02:00)
            $formatter = new LineFormatter($format, $date, true, true);
            $handler->setFormatter($formatter);

            $logger->pushHandler($handler);
            self::$instances[$channel] = $logger;
        }

        return self::$instances[$channel];
    }

    /**
     * Validates a logging channel name.
     *
     * The channel must start with a lowercase letter and may contain
     * only lowercase letters, digits, underscores and hyphens.
     *
     * @throws RuntimeException If the channel name is invalid.
     */
    private static function validateChannel(string $channel): string
    {
        if ($channel === '') {
            throw new RuntimeException(
                'Log channel must not be empty.'
            );
        }

        if (preg_match(self::CHANNEL_PATTERN, $channel) !== 1) {
            throw new RuntimeException(
                sprintf(
                    'Invalid log channel "%s". Only lowercase letters, digits, underscores and hyphens are allowed, and the name must start with a letter.',
                    $channel
                )
            );
        }

        return $channel;
    }

    private static function resolveLogLevel(): Level
    {
        $value = $_ENV['LOG_LEVEL'] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                'LOG_LEVEL must be defined as a non-empty string.'
            );
        }

        $level = strtoupper(trim($value));

        return match ($level) {
            'DEBUG'     => Level::Debug,
            'INFO'      => Level::Info,
            'NOTICE'    => Level::Notice,
            'WARNING'   => Level::Warning,
            'ERROR'     => Level::Error,
            'CRITICAL'  => Level::Critical,
            'ALERT'     => Level::Alert,
            'EMERGENCY' => Level::Emergency,
            default     => throw new RuntimeException(
                sprintf(
                    'Unsupported LOG_LEVEL value "%s". Allowed values: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY.',
                    $level
                )
            ),
        };
    }

    public static function reset(): void
    {
        self::$instances = [];
    }

    /**
     * Logs a message based on an error code,
     * then returns the associated human-readable message.
     *
     * @phpstan-param array<string, scalar|\Stringable|array<int|string, mixed>|null> $context
     */
    public static function logCodeAndGetMessage(
        string $channel,
        string $level,
        string $code,
        array $context = []
    ): string {
        $logger    = self::getLogger($channel);
        $message   = MessageManager::get($code);
        $formatted = sprintf('[%s] %s', $code, $message);

        if (method_exists($logger, $level)) {
            $logger->{$level}($formatted, $context);
            return $message;
        }

        $logger->info($formatted, $context);

        return $message;
    }
}
