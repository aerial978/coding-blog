<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;

final class UserController
{
    public function __construct(
        private UserModelInterface $userModel,
        private ResponderInterface $responder,
    ) {
    }

    public function index(): void
    {
        $users = array_map(
            static fn (UserEntity $user): array => [
                'user_id'    => $user->getUserId(),
                'username'   => $user->getUsername(),
                'email'      => $user->getEmail(),
                'role'       => $user->getRole(),
                'status'     => $user->getStatus(),
                'created_at' => $user->getCreatedAt(),
            ],
            $this->userModel->findAll()
        );

        $this->responder->render('admin/users/index.html.twig', [
            'title' => 'Gestion des utilisateurs',
            'users' => $users,
        ]);
    }
}
