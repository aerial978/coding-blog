<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Contract\ResponderInterface;
use App\Service\Account\Contract\AccountServiceInterface;

final class AccountController
{
    public function __construct(
        private ResponderInterface $responder,
        private AccountServiceInterface $accountService,
    ) {
    }

    public function index(): void
    {
        $user = $this->accountService->getCurrentUser();

        if ($user === null) {
            $this->responder->redirect('/login');
            return;
        }

        $this->responder->render('account/index.html.twig', [
            'title'   => 'Mon compte',
            'account' => [
                'username'          => $user->getUsername(),
                'email'             => $user->getEmail(),
                'email_2fa_enabled' => $user->isEmail2faEnabled(),
            ],
        ]);
    }
}
