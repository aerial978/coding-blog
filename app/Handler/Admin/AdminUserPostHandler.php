<?php

declare(strict_types=1);

namespace App\Handler\Admin;

use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Service\Admin\Contract\AdminUserServiceInterface;

final class AdminUserPostHandler
{
    public function __construct(
        private AdminUserServiceInterface $adminUserService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private ErrorController $errorController,
    ) {
    }

    /**
     * @param array<string, mixed> $form
     */
    public function handle(string $id, array $form): void
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->errorController->notFound();
            return;
        }

        $userId = (int) $id;
        $result = $this->adminUserService->update($userId, $form);

        if ($this->hasErrors($result)) {
            $this->handleErrors($result);
            $this->responder->redirect('/admin/users/' . $userId . '/edit');
            return;
        }

        $this->flash->add(
            'success',
            MessageManager::get(ErrorCode::ADMIN_USER_UPDATE_SUCCESS)
        );

        $this->responder->redirect('/admin/users');
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hasErrors(array $result): bool
    {
        return isset($result['errors']) && is_array($result['errors']);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function handleErrors(array $result): void
    {
        /** @var array<mixed> $errors */
        $errors = $result['errors'];

        foreach ($errors as $errorCode) {
            if (is_string($errorCode)) {
                $this->flash->add(
                    'error',
                    MessageManager::get($errorCode)
                );
            }
        }

        if (isset($result['old']) && is_array($result['old'])) {
            $this->flash->put('old', $result['old']);
        }
    }
}
