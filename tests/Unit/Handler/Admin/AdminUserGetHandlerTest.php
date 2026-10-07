<?php

declare(strict_types=1);

namespace Tests\Unit\Handler\Admin;

use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Handler\Admin\AdminUserGetHandler;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminUserGetHandlerTest extends TestCase
{
    private UserModelInterface&MockObject $userModel;
    private ResponderInterface&MockObject $responder;
    private ErrorController&MockObject $errorController;
    private CsrfTokenInterface&MockObject $csrf;
    private FlashInterface&MockObject $flash;
    private AuthCheckerInterface&MockObject $authChecker;

    private AdminUserGetHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userModel       = $this->createMock(UserModelInterface::class);
        $this->responder       = $this->createMock(ResponderInterface::class);
        $this->errorController = $this->createMock(ErrorController::class);
        $this->csrf            = $this->createMock(CsrfTokenInterface::class);
        $this->flash           = $this->createMock(FlashInterface::class);
        $this->authChecker     = $this->createMock(AuthCheckerInterface::class);

        $this->handler = new AdminUserGetHandler(
            $this->userModel,
            $this->responder,
            $this->errorController,
            $this->csrf,
            $this->flash,
            $this->authChecker,
        );
    }

    public function testRendersSelectedUserWithCsrfToken(): void
    {
        $user = (new UserEntity())->hydrate([
            'user_id'           => 228,
            'username'          => 'alice',
            'slug'              => 'alice',
            'email'             => 'alice@example.com',
            'password'          => 'hashed-password',
            'role'              => 'ADMIN',
            'status'            => 'active',
            'email_2fa_enabled' => 0,
            'created_at'        => '2026-01-01 10:00:00',
            'updated_at'        => '2026-01-02 11:00:00',
        ]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->errorController
            ->expects($this->never())
            ->method('notFound');

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::ADMIN_USER_EDIT)
            ->willReturn('csrf-admin-user-edit-token');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(100);

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'admin/users/edit.html.twig',
                [
                    'title'          => 'Modifier un utilisateur',
                    'csrf_token'     => 'csrf-admin-user-edit-token',
                    'is_own_account' => false,
                    'user'           => [
                        'user_id'    => 228,
                        'username'   => 'alice',
                        'email'      => 'alice@example.com',
                        'role'       => 'ADMIN',
                        'status'     => 'active',
                        'created_at' => '2026-01-01 10:00:00',
                    ],
                ]
            );

        $this->flash
            ->method('take')
            ->with('old', [])
            ->willReturn([]);

        $this->handler->handle('228');
    }

    public function testReturns404ForInvalidUserId(): void
    {
        $this->userModel
            ->expects($this->never())
            ->method('findOneById');

        $this->csrf
            ->expects($this->never())
            ->method('generateToken');

        $this->responder
            ->expects($this->never())
            ->method('render');

        $this->errorController
            ->expects($this->once())
            ->method('notFound');

        $this->authChecker
            ->expects($this->never())
            ->method('getUserId');

        $this->flash
            ->method('take')
            ->with('old', [])
            ->willReturn([]);

        $this->handler->handle('abc');
    }

    public function testReturns404WhenUserDoesNotExist(): void
    {
        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(999999)
            ->willReturn(null);

        $this->csrf
            ->expects($this->never())
            ->method('generateToken');

        $this->responder
            ->expects($this->never())
            ->method('render');

        $this->errorController
            ->expects($this->once())
            ->method('notFound');

        $this->authChecker
            ->expects($this->never())
            ->method('getUserId');

        $this->flash
            ->method('take')
            ->with('old', [])
            ->willReturn([]);

        $this->handler->handle('999999');
    }

    public function testRendersOldEditableValuesAfterValidationError(): void
    {
        $user = (new UserEntity())->hydrate([
            'user_id'           => 228,
            'username'          => 'alice',
            'slug'              => 'alice',
            'email'             => 'alice@example.com',
            'password'          => 'hashed-password',
            'role'              => 'ADMIN',
            'status'            => 'active',
            'email_2fa_enabled' => 0,
            'created_at'        => '2026-01-01 10:00:00',
            'updated_at'        => '2026-01-02 11:00:00',
        ]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('old', [])
            ->willReturn([
                'username' => 'alice_updated',
                'role'     => 'MEMBER',
                'status'   => 'disabled',
            ]);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::ADMIN_USER_EDIT)
            ->willReturn('csrf-admin-user-edit-token');

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(100);

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'admin/users/edit.html.twig',
                [
                    'title'          => 'Modifier un utilisateur',
                    'csrf_token'     => 'csrf-admin-user-edit-token',
                    'is_own_account' => false,
                    'user'           => [
                        'user_id'    => 228,
                        'username'   => 'alice_updated',
                        'email'      => 'alice@example.com',
                        'role'       => 'MEMBER',
                        'status'     => 'disabled',
                        'created_at' => '2026-01-01 10:00:00',
                    ],
                ]
            );

        $this->handler->handle('228');
    }

    public function testRendersOwnAccountFlagWhenEditingAuthenticatedUser(): void
    {
        $user = (new UserEntity())->hydrate([
            'user_id'           => 228,
            'username'          => 'admin',
            'slug'              => 'admin',
            'email'             => 'admin@example.com',
            'password'          => 'hashed-password',
            'role'              => 'ADMIN',
            'status'            => 'active',
            'email_2fa_enabled' => 0,
            'created_at'        => '2026-01-01 10:00:00',
            'updated_at'        => '2026-01-02 11:00:00',
        ]);

        $this->userModel
            ->expects($this->once())
            ->method('findOneById')
            ->with(228)
            ->willReturn($user);

        $this->flash
            ->expects($this->once())
            ->method('take')
            ->with('old', [])
            ->willReturn([]);

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(228);

        $this->csrf
            ->expects($this->once())
            ->method('generateToken')
            ->with(FormId::ADMIN_USER_EDIT)
            ->willReturn('csrf-admin-user-edit-token');

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'admin/users/edit.html.twig',
                [
                    'title'          => 'Modifier un utilisateur',
                    'csrf_token'     => 'csrf-admin-user-edit-token',
                    'is_own_account' => true,
                    'user'           => [
                        'user_id'    => 228,
                        'username'   => 'admin',
                        'email'      => 'admin@example.com',
                        'role'       => 'ADMIN',
                        'status'     => 'active',
                        'created_at' => '2026-01-01 10:00:00',
                    ],
                ]
            );

        $this->handler->handle('228');
    }
}
