<?php

declare(strict_types=1);

namespace App\Handler\Admin;

use App\Controller\ErrorController;
use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\CsrfTokenInterface;

final class AdminUserGetHandler
{
    public function __construct(
        private UserModelInterface $userModel,
        private ResponderInterface $responder,
        private ErrorController $errorController,
        private CsrfTokenInterface $csrf,
    ) {
    }

    public function handle(string $id): void
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->errorController->notFound();
            return;
        }

        $user = $this->userModel->findOneById((int) $id);

        if ($user === null) {
            $this->errorController->notFound();
            return;
        }

        $this->responder->render('admin/users/edit.html.twig', [
            'title'          => $user->getRole() === 'ADMIN' ? 'Consulter un administrateur' : 'Modifier un utilisateur',
            'active_nav'     => 'users',
            'csrf_token'     => $this->csrf->generateToken(FormId::ADMIN_USER_EDIT),
            'user'           => [
                'user_id'    => $user->getUserId(),
                'username'   => $user->getUsername(),
                'email'      => $user->getEmail(),
                'role'       => $user->getRole(),
                'status'     => $user->getStatus(),
                'created_at' => $user->getCreatedAt(),
            ],
        ]);
    }
}
