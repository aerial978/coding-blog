<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Admin;

use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Handler\Admin\AdminUserDeleteHandler;
use App\Http\Contract\ResponderInterface;
use App\Service\Admin\Contract\AdminUserDeletionServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminUserDeleteHandlerTest extends TestCase
{
    private AdminUserDeletionServiceInterface&MockObject $deletionService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;
    private ErrorController&MockObject $errorController;

    private AdminUserDeleteHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deletionService = $this->createMock(
            AdminUserDeletionServiceInterface::class
        );
        $this->flash           = $this->createMock(FlashInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);
        $this->errorController = $this->createMock(ErrorController::class);

        $this->handler = new AdminUserDeleteHandler(
            $this->deletionService,
            $this->flash,
            $this->responder,
            $this->errorController,
        );
    }

    public function testHandleReturns404ForInvalidUserId(): void
    {
        $this->deletionService
            ->expects($this->never())
            ->method('delete');

        $this->errorController
            ->expects($this->once())
            ->method('notFound');

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $this->handler->handle('abc');
    }

    public function testHandleRedirectsToUserListOnSuccess(): void
    {
        $this->deletionService
            ->expects($this->once())
            ->method('delete')
            ->with(228)
            ->willReturn([]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'success',
                MessageManager::get(ErrorCode::ADMIN_USER_DELETE_SUCCESS)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/admin/users');

        $this->handler->handle('228');
    }

    public function testHandleRedirectsWithDeletionError(): void
    {
        $this->deletionService
            ->expects($this->once())
            ->method('delete')
            ->with(228)
            ->willReturn([
                'errors' => [
                    ErrorCode::ADMIN_USER_NOT_FOUND,
                ],
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::ADMIN_USER_NOT_FOUND)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/admin/users');

        $this->handler->handle('228');
    }

    public function testHandleRejectsAdminAccountDeletion(): void
    {
        $this->deletionService
            ->expects($this->once())
            ->method('delete')
            ->with(10)
            ->willReturn([
                'errors' => [
                    ErrorCode::ADMIN_USER_DELETE_FORBIDDEN,
                ],
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with(
                'error',
                MessageManager::get(ErrorCode::ADMIN_USER_DELETE_FORBIDDEN)
            );

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/admin/users');

        $this->handler->handle('10');
    }
}
