<?php

namespace App\Core;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonoLogger;
use Psr\Log\LoggerInterface;

/**
 * Logger factory and manager for the application.
 *
 * This class provides PSR-3 compatible loggers (via Monolog) and ensures
 * that each logging channel has its own instance and file.
 */
class Logger
{
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
     * @param string $channel The logging channel name (e.g., "app", "db", "user").
     * @return LoggerInterface A PSR-3 logger instance.
     */
    public static function getLogger(string $channel = 'app'): LoggerInterface
    {
        if (!isset(self::$instances[$channel])) {
            $logger = new MonoLogger($channel);

            $baseLogDir    = dirname(__DIR__, 2) . '/Logs';
            $channelLogDir = $baseLogDir . '/' . $channel;

            if (!is_dir($channelLogDir)) {
                mkdir($channelLogDir, 0755, true);
            }

            $logFile = $channelLogDir . '/' . $channel . '.log';

            $handler = new RotatingFileHandler($logFile, 14, MonoLogger::DEBUG);

            // Formatter : supprime %context% et %extra% quand ils sont vides
            $format    = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
            $date      = 'c'; // ISO8601 (ex : 2025-08-23T14:33:12+02:00)
            $formatter = new LineFormatter($format, $date, true, true); // ← ignoreEmptyContextAndExtra = true
            $handler->setFormatter($formatter);

            $logger->pushHandler($handler);
            self::$instances[$channel] = $logger;
        }

        return self::$instances[$channel];
    }

    public static function reset(): void
    {
        self::$instances = [];
    }

    /**
     * Logs a message based on an error code,
     * then returns the associated human-readable message.
     *
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
