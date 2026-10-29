<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FormId;
use App\Handler\Account\AccountUsernameUpdateHandler;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Security\Contract\CsrfTokenInterface;
use App\Service\Account\Contract\AccountServiceInterface;

final class AccountController
{
    public function __construct(
        private ResponderInterface $responder,
        private AccountServiceInterface $accountService,
        private AccountUsernameUpdateHandler $updateHandler,
        private Request $request,
        private CsrfTokenInterface $csrf,
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
            'title'      => 'Mon compte',
            'active_nav' => 'account',
            'csrf_token' => $this->csrf->generateToken(FormId::ACCOUNT_USERNAME_UPDATE),
            'account'    => [
                'username'          => $user->getUsername(),
                'email'             => $user->getEmail(),
                'email_2fa_enabled' => $user->isEmail2faEnabled(),
                'role'              => $user->getRole(),
                'status'            => $user->getStatus(),
                'created_at'        => $user->getCreatedAt(),
            ],
        ]);
    }

    public function updateUsername(): void
    {
        $user = $this->accountService->getCurrentUser();

        if ($user === null) {
            $this->responder->redirect('/login');
            return;
        }

        $form = $this->request->request();

        $this->updateHandler->handle($form);
    }
}
