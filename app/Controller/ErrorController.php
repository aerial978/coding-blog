<?php

namespace App\Controller;

/**
 * Controller dedicated to handling HTTP errors.
 */
class ErrorController extends BaseController
{
    /**
     * Displays a 404 page.
     */
    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404.html.twig');
    }

    /**
     * Displays a 500 page.
     */
    public function serverError(): void
    {
        http_response_code(500);
        $this->render('errors/500.html.twig');
    }
}
