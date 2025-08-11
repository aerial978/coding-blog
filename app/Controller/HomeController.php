<?php

namespace App\Controller;

use App\Model\UserModel;

class HomeController extends BaseController
{
    /**
     * Constructor.
     *
     * Initializes the controller with the UserModel dependency.
     *
     * @param UserModel $userModel The model used to interact with user data.
     */
    public function __construct(private UserModel $userModel)
    {
        parent::__construct();
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

        $this->render('home/index.html.twig', [
            'title'   => 'Home',
            'message' => 'This is the home page.',
            'users'   => $users,
        ]);
    }
}
