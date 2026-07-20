<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Contract\ResponderInterface;

/**
 * Controller dedicated to handling HTTP errors.
 */
class ErrorController
{
    public function __construct(
        private ResponderInterface $responder,
    ) {
    }

    /**
     * Displays a 404 page.
     */
    public function notFound(): void
    {
        http_response_code(404);

        $this->responder->render('errors/404.html.twig');
    }

    /**
     * Displays a 500 page.
     */
    public function serverError(?string $errorId = null): void
    {
        http_response_code(500);

        $this->responder->render('errors/500.html.twig', [
            'errorId' => $errorId,
        ]);
    }
}
