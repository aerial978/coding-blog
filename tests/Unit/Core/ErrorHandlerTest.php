<?php

namespace Tests\Unit\Core;

use App\Controller\ErrorController;
use App\Core\ErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Unit tests for the ErrorHandler class.
 *
 * This suite verifies that:
 * - Error and exception handlers are correctly registered.
 * - Exceptions are handled differently in production and local environments.
 * - Fatal errors are logged and routed to the correct controller or displayed as debug HTML.
 * - The shutdown handler works without issues when no fatal error occurred.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_ENV'] = 'prod';
    }

    public function testRegisterSetsHandlers(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        ErrorHandler::register($logger);

        try {
            $this->expectException(\ErrorException::class);
            trigger_error('Error triggered by test', E_USER_WARNING);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleExceptionInProductionCallsErrorController(): void
    {
        $mockController = $this->createMock(ErrorController::class);
        $mockController
            ->expects($this->once())
            ->method('serverError');

        ErrorHandler::setErrorController($mockController);

        ob_start();
        ErrorHandler::handleException(new \RuntimeException('Simulated error'));
        ob_end_clean();
    }

    public function testHandleExceptionInLocalRendersDebugOutput(): void
    {
        $_ENV['APP_ENV'] = 'local';

        ob_start();
        ErrorHandler::handleException(new \RuntimeException('Local error'));
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('<h1>Caught Exception</h1>', $output);
        $this->assertStringContainsString('Local error', $output);
        $this->assertStringContainsString('Error Code', $output);
    }

    public function testHandleErrorThrowsErrorException(): void
    {
        $this->expectException(\ErrorException::class);
        ErrorHandler::handleError(E_USER_WARNING, 'Warning simulated', __FILE__, __LINE__);
    }

    public function testHandleFatalErrorInProductionCallsErrorController(): void
    {
        $_ENV['APP_ENV'] = 'prod';

        $mockController = $this->createMock(ErrorController::class);
        $mockController
            ->expects($this->once())
            ->method('serverError');

        ErrorHandler::setErrorController($mockController);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains('Fatal error detected'),
                $this->arrayHasKey('message')
            );

        ErrorHandler::register($logger);

        $reflection = new ReflectionClass(ErrorHandler::class);
        $method     = $reflection->getMethod('handleFatalError');
        $method->setAccessible(true);

        $fakeError = [
            'type'    => E_ERROR,
            'message' => 'Error simulated',
            'file'    => 'File.php',
            'line'    => 42
        ];

        ob_start();
        try {
            $method->invoke(null, $fakeError);
        } finally {
            ob_end_clean();
            restore_exception_handler();
            restore_error_handler();
        }
    }

    public function testHandleFatalErrorInLocalDisplaysDebugHtml(): void
    {
        $_ENV['APP_ENV'] = 'local';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        ErrorHandler::register($logger);

        $reflection = new ReflectionClass(ErrorHandler::class);
        $method     = $reflection->getMethod('handleFatalError');
        $method->setAccessible(true);

        $fakeError = [
            'type'    => E_ERROR,
            'message' => 'Simulated local error',
            'file'    => 'Error.php',
            'line'    => 99
        ];

        ob_start();
        $method->invoke(null, $fakeError);
        $output = ob_get_clean() ?: '';

        $this->assertStringContainsString('<h1>Fatal Error</h1>', $output);
        $this->assertStringContainsString('Simulated local error', $output);

        restore_exception_handler();
        restore_error_handler();
    }

    public function testHandleShutdownWithoutFatalError(): void
    {
        ob_start();
        ErrorHandler::handleShutdown();
        $output = ob_get_clean() ?: '';

        $this->assertStringNotContainsString('<h1>Fatal Error</h1>', $output);
        $this->assertStringNotContainsString('Caught Exception', $output);
    }
}
