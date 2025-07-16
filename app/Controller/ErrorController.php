<?php

namespace App\Controller;

/**
 * Controller dedicated to handling HTTP errors.
 */
class ErrorController
{
    /**
     * Displays a 404 page.
     */
    public function notFound(): void
    {
        http_response_code(404);
        echo '<h3>404 - Page introuvable</h3>';
    }

    /**
     * Displays a 500 page.
     */
    public function serverError(): void
    {
        http_response_code(500);
        echo '<h3>500 - Erreur interne du serveur</h3>';
    }
}
