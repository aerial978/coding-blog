<?php

declare(strict_types=1);

namespace App\Handler\Account;

use App\Core\Contract\FlashInterface;
use App\Core\ErrorCode;
use App\Core\MessageManager;
use App\Http\Contract\ResponderInterface;
use App\Service\Account\Contract\AccountUsernameUpdateServiceInterface;

final class AccountUsernameUpdateHandler
{
    public function __construct(
        private AccountUsernameUpdateServiceInterface $updateService,
        private FlashInterface $flash,
        private ResponderInterface $responder,
    ) {
    }

    /**
     * @param array<string, mixed> $form
     */
    public function handle(array $form): void
    {
        $result = $this->updateService->update($form);

        if (isset($result['errors'])) {
            foreach ($result['errors'] as $errorCode) {
                $this->flash->add(
                    'error',
                    MessageManager::get($errorCode)
                );
            }

            $this->responder->redirect('/account');
            return;
        }

        $this->flash->add(
            'success',
            MessageManager::get(ErrorCode::ACCOUNT_USERNAME_UPDATE_SUCCESS)
        );

        $this->responder->redirect('/account');
    }
}
