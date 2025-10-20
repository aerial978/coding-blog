<?php

namespace App\Core;

use App\Controller\ErrorController;
use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Centralized error and exception handler for the application.
 *
 * This class is responsible for:
 * - Capturing uncaught exceptions and PHP errors
 * - Logging error details
 * - Displaying appropriate error pages (or debug output in local mode)
 * - Handling fatal errors during shutdown
 */
class ErrorHandler
{
    /** @var ErrorController|null Handles displaying error pages */
    private static ?ErrorController $errorController = null;

    /** @var LoggerInterface|null Logger used to record error details */
    private static ?LoggerInterface $logger = null;

    /**
     * Registers this class as the global error and exception handler.
     *
     * @param LoggerInterface $logger The PSR-3 logger instance.
     */
    public static function register(LoggerInterface $logger): void
    {
        self::$logger = $logger;

        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Handles uncaught exceptions.
     *
     * Logs the exception and either shows a generic error page (in production)
     * or a detailed debug view (in local mode).
     *
     * @param Throwable $exception The thrown exception.
     */
    public static function handleException(Throwable $exception): void
    {
        $uniqueErrorId = uniqid('ERR-', true);

        self::$logger?->error('Uncaught exception', [
            'message' => $exception->getMessage(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'trace'   => $exception->getTraceAsString(),
        ]);

        http_response_code(500);

        $appConfig = new AppConfig();

        if (!$appConfig->isLocal()) {
            // Use the injected controller if available; otherwise render a basic 500 page.
            if (self::$errorController) {
                self::$errorController->serverError($uniqueErrorId);
                return;
            }

            self::renderGeneric500($uniqueErrorId);
            return;
        }

        self::renderDebug($exception, $uniqueErrorId);
    }

    /**
     * Converts PHP errors into ErrorException instances so they can be caught.
     *
     * @param int    $severity The error severity.
     * @param string $message  The error message.
     * @param string $file     The file where the error occurred.
     * @param int    $line     The line number where the error occurred.
     * @return bool Always throws an exception, so never returns.
     * @throws ErrorException
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handles fatal errors detected during shutdown.
     *
     * @param array{type: int, message: string, file: string, line: int}|null $lastError
     */
    private static function handleFatalError(?array $lastError): void
    {
        if ($lastError && in_array($lastError['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            self::$logger?->critical('Fatal error detected', [
                'message' => $lastError['message'],
                'file'    => $lastError['file'],
                'line'    => $lastError['line'],
            ]);

            http_response_code(500);

            $appConfig = new AppConfig();

            if (!$appConfig->isLocal()) {
                if (self::$errorController) {
                    // serverError can accept null/omitted id if your method signature allows it
                    self::$errorController->serverError();
                    return;
                }

                self::renderGeneric500(null);
                return;
            }

            echo '<h1>Fatal Error</h1>';
            echo "<p><strong>Message:</strong> {$lastError['message']}</p>";
            echo "<p><strong>File:</strong> {$lastError['file']}</p>";
            echo "<p><strong>Line:</strong> {$lastError['line']}</p>";
        }
    }

    /**
     * Shutdown handler to check for fatal errors.
     */
    public static function handleShutdown(): void
    {
        $lastError = error_get_last();
        self::handleFatalError($lastError);
    }

    /**
     * Renders a detailed debug page for exceptions (local mode only).
     *
     * @param Throwable $exception   The exception to display.
     * @param string    $uniqueErrorId A unique error identifier.
     */
    private static function renderDebug(Throwable $exception, string $uniqueErrorId): void
    {
        echo '<h1>Caught Exception</h1>';
        echo "<p><strong>Error Code:</strong> {$uniqueErrorId}</p>";
        echo "<p><strong>Message:</strong> {$exception->getMessage()}</p>";
        echo "<p><strong>File:</strong> {$exception->getFile()}</p>";
        echo "<p><strong>Line:</strong> {$exception->getLine()}</p>";
        echo "<pre>{$exception->getTraceAsString()}</pre>";
    }

    /**
     * Sets a custom error controller instance.
     *
     * @param ErrorController|null $controller The controller to handle errors.
     */
    public static function setErrorController(?ErrorController $controller): void
    {
        self::$errorController = $controller;
    }

    /**
     * Renders a minimal generic 500 page for production when no controller is set.
     *
     * @param string|null $uniqueErrorId
     */
    private static function renderGeneric500(?string $uniqueErrorId): void
    {
        echo '<h1>Something went wrong</h1>';
        echo '<p>An unexpected error occurred. Please try again later.</p>';
        if ($uniqueErrorId !== null) {
            echo "<p>Error code: <code>{$uniqueErrorId}</code></p>";
        }
    }
}
