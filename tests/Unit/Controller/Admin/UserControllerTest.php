<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use App\Controller\Admin\UserController;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserControllerTest extends TestCase
{
    private UserModelInterface&MockObject $userModel;

    private ResponderInterface&MockObject $responder;

    private UserController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userModel = $this->createMock(UserModelInterface::class);
        $this->responder = $this->createMock(ResponderInterface::class);

        $this->controller = new UserController(
            $this->userModel,
            $this->responder,
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
}
