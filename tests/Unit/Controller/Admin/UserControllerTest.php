<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use App\Controller\Admin\UserController;
use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\FormId;
use App\Core\MessageManager;
use App\Handler\Admin\AdminUserGetHandler;
use App\Handler\Admin\AdminUserPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Admin\Contract\AdminUserServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserControllerTest extends TestCase
{
    private UserModelInterface&MockObject $userModel;
    private ResponderInterface&MockObject $responder;
    private Request&MockObject $request;
    private ErrorController&MockObject $errorController;
    private FlashInterface&MockObject $flash;
    private CsrfTokenInterface&MockObject $csrf;
    private AuthCheckerInterface&MockObject $authChecker;
    private AdminUserServiceInterface&MockObject $adminUserService;

    private UserController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userModel        = $this->createMock(UserModelInterface::class);
        $this->responder        = $this->createMock(ResponderInterface::class);
        $this->request          = $this->createMock(Request::class);
        $this->errorController  = $this->createMock(ErrorController::class);
        $this->flash            = $this->createMock(FlashInterface::class);
        $this->csrf             = $this->createMock(CsrfTokenInterface::class);
        $this->authChecker      = $this->createMock(AuthCheckerInterface::class);
        $this->adminUserService = $this->createMock(AdminUserServiceInterface::class);

        $getHandler = new AdminUserGetHandler(
            $this->userModel,
            $this->responder,
            $this->errorController,
            $this->csrf,
            $this->flash,
            $this->authChecker,
        );

        $postHandler = new AdminUserPostHandler(
            $this->adminUserService,
            $this->flash,
            $this->responder,
            $this->errorController,
        );

        $this->controller = new UserController(
            $this->userModel,
            $this->responder,
            $this->request,
            $getHandler,
            $postHandler,
        );
    }

    public function testIndexRendersUserList(): void
    {
        $users = [
            (new UserEntity())->hydrate([
                'user_id'    => 1,
                'username'   => 'alice',
                'email'      => 'alice@example.com',
                'role'       => 'ADMIN',
                'status'     => 'active',
                'created_at' => '2026-01-01 10:00:00',
            ]),
            (new UserEntity())->hydrate([
                'user_id'    => 2,
                'username'   => 'bob',
                'email'      => 'bob@example.com',
                'role'       => 'MEMBER',
                'status'     => 'inactive',
                'created_at' => '2026-01-02 11:00:00',
            ]),
        ];

        $this->userModel
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($users);

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'admin/users/index.html.twig',
                [
                    'title' => 'Gestion des utilisateurs',
                    'users' => [
                        [
                            'user_id'    => 1,
                            'username'   => 'alice',
                            'email'      => 'alice@example.com',
                            'role'       => 'ADMIN',
                            'status'     => 'active',
                            'created_at' => '2026-01-01 10:00:00',
                        ],
                        [
                            'user_id'    => 2,
                            'username'   => 'bob',
                            'email'      => 'bob@example.com',
                            'role'       => 'MEMBER',
                            'status'     => 'inactive',
                            'created_at' => '2026-01-02 11:00:00',
                        ],
                    ],
                ]
            );

        $this->controller->index();
    }

    public function testIndexRendersEmptyUserList(): void
    {
        $this->userModel
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->responder
            ->expects($this->once())
            ->method('render')
            ->with(
                'admin/users/index.html.twig',
                [
                    'title' => 'Gestion des utilisateurs',
                    'users' => [],
                ]
            );

        $this->controller->index();
    }


    public function testUpdateDelegatesSubmittedFormToPostHandler(): void
    {
        $form = [
            'username' => 'alice_updated',
            'role'     => 'MEMBER',
            'status'   => 'active',
        ];

        $this->request
            ->expects($this->once())
            ->method('request')
            ->willReturn($form);

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
            ->with('success', MessageManager::get(ErrorCode::ADMIN_USER_UPDATE_SUCCESS));

        $this->responder
            ->expects($this->once())
            ->method('redirect')
            ->with('/admin/users');

        $this->controller->update('228');
    }

    public function testEditDelegatesToGetHandler(): void
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

        $this->authChecker
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(100);

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

        $this->controller->edit('228');
    }
}
