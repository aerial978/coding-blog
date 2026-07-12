<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Account\Contract\AccountServiceInterface;

final class AccountController
{
    public function __construct(
        private CsrfTokenInterface $csrf,
        private ResponderInterface $responder,
        private AccountServiceInterface $accountService,
    ) {
    }

    public function index(): void
    {
        $user = $this->accountService->getCurrentUser();

        if ($user === null) {
            $this->responder->redirect('/coding-blog/login');
            return;
        }

        $this->responder->render('account/index.html.twig', [
            'title' => 'Mon compte',
            'account' => [
                'username'          => $user->getUsername(),
                'email'             => $user->getEmail(),
                'email_2fa_enabled' => $user->isEmail2faEnabled(),
            ],
            'logout_csrf_token' => $this->csrf->generateToken(FormId::LOGOUT),
        ]);
    }
}