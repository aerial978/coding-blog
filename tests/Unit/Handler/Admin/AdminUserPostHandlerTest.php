<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Admin;

use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Handler\Admin\AdminUserPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Service\Admin\Contract\AdminUserServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminUserPostHandlerTest extends TestCase
{
    private ErrorController&MockObject $errorController;
    private AdminUserServiceInterface&MockObject $adminUserService;
    private FlashInterface&MockObject $flash;
    private ResponderInterface&MockObject $responder;

    private AdminUserPostHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUserService = $this->createMock(AdminUserServiceInterface::class);
        $this->flash            = $this->createMock(FlashInterface::class);
        $this->responder        = $this->createMock(ResponderInterface::class);
        $this->errorController  = $this->createMock(ErrorController::class);

        $this->handler = new AdminUserPostHandler(
            $this->adminUserService,
            $this->flash,
            $this->responder,
            $this->errorController,
        );
    }

    public function testHandleRedirectsBackWithErrorsAndOldInput(): void
    {
        $form = [
            'username' => 'invalid username',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ];

        $old = [
            'username' => 'invalid username',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ];

        $this->adminUserService
            ->expects($this->once())
            ->method('update')
            ->with(228, $form)
            ->willReturn([
                'errors' => [
                    'username' => ErrorCode::ADMIN_USER_USERNAME_INVALID,
                ],
                'old' => $old,
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', $old);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/admin/users/228/edit');

        $this->handler->handle('228', $form);
    }

    public function testHandleRedirectsBackWithErrorWithoutOldInput(): void
    {
        $form = [
            'username' => 'alice',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ];

        $this->adminUserService
            ->expects($this->once())
            ->method('update')
            ->with(999999, $form)
            ->willReturn([
                'errors' => [
                    ErrorCode::ADMIN_USER_NOT_FOUND,
                ],
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/admin/users/999999/edit');

        $this->handler->handle('999999', $form);
    }

    public function testHandleAddsAllReturnedErrors(): void
    {
        $form = [
            'username' => '',
            'role'     => 'INVALID',
            'status'   => 'invalid',
        ];

        $this->adminUserService
            ->expects($this->once())
            ->method('update')
            ->with(228, $form)
            ->willReturn([
                'errors' => [
                    'username' => ErrorCode::ADMIN_USER_USERNAME_REQUIRED,
                    'role'     => ErrorCode::ADMIN_USER_ROLE_INVALID,
                    'status'   => ErrorCode::ADMIN_USER_STATUS_INVALID,
                ],
                'old' => $form,
            ]);

        $this->flash
            ->expects($this->exactly(3))
            ->method('add')
            ->with('error', $this->isType('string'));

        $this->flash
            ->expects($this->once())
            ->method('put')
            ->with('old', $form);

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/admin/users/228/edit');

        $this->handler->handle('228', $form);
    }

    public function testHandleRedirectsToUserListOnSuccess(): void
    {
        $form = [
            'username' => 'alice_updated',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ];

        $this->adminUserService
            ->expects($this->once())
            ->method('update')
            ->with(228, $form)
            ->willReturn([
                'ok' => true,
            ]);

        $this->flash
            ->expects($this->once())
            ->method('add')
            ->with('success', $this->isType('string'));

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/admin/users');

        $this->handler->handle('228', $form);
    }

    public function testHandleReturns404ForInvalidUserId(): void
    {
        $form = [
            'username' => 'alice',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ];

        $this->adminUserService
            ->expects($this->never())
            ->method('update');

        $this->flash
            ->expects($this->never())
            ->method('add');

        $this->flash
            ->expects($this->never())
            ->method('put');

        $this->responder
            ->expects($this->never())
            ->method('redirect');

        $this->errorController
            ->expects($this->once())
            ->method('notFound');

        $this->handler->handle('abc', $form);
    }
}
