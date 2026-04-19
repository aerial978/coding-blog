<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Core\View;
use App\Security\Contract\CsrfTokenInterface;

final class AccountController extends BaseController
{
    public function __construct(
        View $view,
        FlashInterface $flash,
        private CsrfTokenInterface $csrf,
    ) {
        parent::__construct($view, $flash);
    }

    public function index(): void
    {
        $this->render('account/index.html.twig', $this->withFlashes([
            'title'             => 'Mon compte',
            'logout_csrf_token' => $this->csrf->generateToken(FormId::LOGOUT),
        ]));
    }
}
