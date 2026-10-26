<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\FormId;
use App\Handler\Admin\AdminUserDeleteHandler;
use App\Handler\Admin\AdminUserGetHandler;
use App\Handler\Admin\AdminUserPostHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;

final class UserController
{
    public function __construct(
        private UserModelInterface $userModel,
        private ResponderInterface $responder,
        private Request $request,
        private AdminUserGetHandler $getHandler,
        private AdminUserPostHandler $postHandler,
        private AdminUserDeleteHandler $deleteHandler,
        private AuthCheckerInterface $authChecker,
        private CsrfTokenInterface $csrf,
    ) {
    }

    public function index(): void
    {
        $currentUserId = $this->authChecker->getUserId();

        $users = array_map(
            static function (UserEntity $user) use ($currentUserId): array {
                $isOwnAccount   = $user->getUserId() === $currentUserId;
                $isAdminAccount = $user->getRole()   === 'ADMIN';

                $deleteDisabledReason = null;

                if ($isOwnAccount) {
                    $deleteDisabledReason = 'Vous ne pouvez pas supprimer votre propre compte.';
                } elseif ($isAdminAccount) {
                    $deleteDisabledReason = 'Vous ne pouvez pas supprimer un compte administrateur.';
                }

                return [
                    'user_id'                => $user->getUserId(),
                    'username'               => $user->getUsername(),
                    'email'                  => $user->getEmail(),
                    'role'                   => $user->getRole(),
                    'status'                 => $user->getStatus(),
                    'created_at'             => $user->getCreatedAt(),
                    'can_delete'             => $deleteDisabledReason === null,
                    'delete_disabled_reason' => $deleteDisabledReason,
                ];
            },
            $this->userModel->findAll()
        );

        $this->responder->render('admin/users/index.html.twig', [
            'title'       => 'Gestion des utilisateurs',
            'active_nav'  => 'users',
            'users'       => $users,
            'csrf_token'  => $this->csrf->generateToken(FormId::ADMIN_USER_DELETE),
        ]);
    }

    public function edit(string $id): void
    {
        $this->getHandler->handle($id);
    }

    public function update(string $id): void
    {
        /** @var array<string, mixed> $form */
        $form = $this->request->request();

        $this->postHandler->handle($id, $form);
    }

    public function delete(string $id): void
    {
        $this->deleteHandler->handle($id);
    }
}
