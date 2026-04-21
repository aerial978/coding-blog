<?php

namespace App\Controller;

use App\Core\Contract\FlashInterface;
use App\Core\FormId;
use App\Core\View;
use App\Http\Request;
use App\Model\Contract\UserModelInterface;
use App\Security\Contract\AuthCheckerInterface;
use App\Security\Contract\CsrfTokenInterface;

class HomeController extends BaseController
{
    /**
     * Constructor.
     *
     * Initializes the controller with the UserModel dependency.
     *
     * @param UserModelInterface $userModel The model used to interact with user data.
     */
    public function __construct(
        View $view,
        private UserModelInterface $userModel,
        FlashInterface $flash,
        private Request $request,
        private AuthCheckerInterface $authChecker,
        private CsrfTokenInterface $csrf,
    ) {
        parent::__construct($view, $flash);
    }

    /**
     * Default action for the home page.
     *
     * Fetches all users from the database and renders the home page template
     * with the list of users and some static page data.
     *
     * @return void
     */
    public function index(): void
    {
        $users = $this->userModel->findAll();

        $isAuthenticated = $this->authChecker->isAuthenticated($this->request);

        $this->render('home/index.html.twig', $this->withFlashes([
            'show_header'       => true,
            'is_authenticated'  => $isAuthenticated,
            'logout_csrf_token' => $isAuthenticated ? $this->csrf->generateToken(FormId::LOGOUT) : '',
            'title'             => 'Home',
            'message'           => 'This is the home page.',
            'users'             => $users,
        ]));
    }
}
