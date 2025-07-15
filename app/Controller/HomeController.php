<?php

namespace App\Controller;

class HomeController
{
    /**
     * Displays the home page
     */
    public function index(): void
    {
        echo '<h1>Bienvenue sur le blog</h1>';
        echo "<p>Ceci est la page d'accueil.</p>";
    }
}
