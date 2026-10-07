<?php

declare(strict_types=1);

namespace App\Handler\Admin;

use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;

final class AdminUserGetHandler
{
    public function __construct(
        private UserModelInterface $userModel,
        private ResponderInterface $responder,
        private ErrorController $errorController,
        private CsrfTokenInterface $csrf,
        private FlashInterface $flash,
        private AuthCheckerInterface $authChecker,
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

        $isOwnAccount = $this->authChecker->getUserId() === $user->getUserId();

        $old = $this->flash->take('old', []);
        $old = is_array($old) ? $old : [];

        /** @var array<string, mixed> $old */

        $this->responder->render('admin/users/edit.html.twig', [
            'title'          => 'Modifier un utilisateur',
            'csrf_token'     => $this->csrf->generateToken(FormId::ADMIN_USER_EDIT),
            'is_own_account' => $isOwnAccount,
            'user'           => [
                'user_id'    => $user->getUserId(),
                'username'   => $this->oldString($old, 'username', $user->getUsername()),
                'email'      => $user->getEmail(),
                'role'       => $this->oldString($old, 'role', $user->getRole()),
                'status'     => $this->oldString($old, 'status', $user->getStatus()),
                'created_at' => $user->getCreatedAt(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $old
     */
    private function oldString(array $old, string $key, ?string $default): ?string
    {
        $value = $old[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
