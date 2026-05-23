<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Security\Contract\CsrfTokenInterface;

final class AccountController
{
    public function __construct(
        private CsrfTokenInterface $csrf,
        private ResponderInterface $responder,
    ) {
    }

    public function index(): void
    {
        $this->responder->render('account/index.html.twig', [
            'title'             => 'Mon compte',
            'logout_csrf_token' => $this->csrf->generateToken(FormId::LOGOUT),
        ]);
    }
}
