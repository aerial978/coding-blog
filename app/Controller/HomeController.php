<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FormId;
use App\Http\Contract\ResponderInterface;
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;

class HomeController
{
    public function __construct(
        private UserModelInterface $userModel,
        private Request $request,
        private AuthCheckerInterface $authChecker,
        private CsrfTokenInterface $csrf,
        private ResponderInterface $responder,
    ) {
    }

    public function index(): void
    {
        $users = $this->userModel->findAll();

        $isAuthenticated = $this->authChecker->isAuthenticated($this->request);

        $this->responder->render('home/index.html.twig', [
            'show_header'       => true,
            'logout_csrf_token' => $isAuthenticated
                ? $this->csrf->generateToken(FormId::LOGOUT)
                : '',
            'title'   => 'Home',
            'message' => 'This is the home page.',
            'users'   => $users,
        ]);
    }
}
