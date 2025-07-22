<?php

namespace App\Controller;

class HomeController extends BaseController
{
    /**
     * Displays the home page
     */
    public function index(): void
    {
        //throw new \Exception('Test erreur 500');

        $this->render('home/index.html.twig', [
            'title'   => 'Bienvenue sur le blog',
            'message' => 'Ceci est la page d\'accueil.',
        ]);
    }
}
