<?php

declare(strict_types=1);

namespace App\Handler\Admin;

use App\Controller\ErrorController;
use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Service\Admin\Contract\AdminUserDeletionServiceInterface;

final class AdminUserDeleteHandler
{
    public function __construct(
        private AdminUserDeletionServiceInterface $deletionService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
        private ErrorController $errorController,
    ) {
    }

    public function handle(string $id): void
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->errorController->notFound();
            return;
        }

        $result = $this->deletionService->delete((int) $id);

        if (isset($result['errors'])) {
            foreach ($result['errors'] as $errorCode) {
                $this->flash->add(
                    'error',
                    MessageManager::get($errorCode)
                );
            }

            $this->responder->redirect('/admin/users');
            return;
        }

        $this->flash->add(
            'success',
            MessageManager::get(ErrorCode::ADMIN_USER_DELETE_SUCCESS)
        );

        $this->responder->redirect('/admin/users');
    }
}
