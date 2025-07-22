<?php

namespace app\Controller;

use Exception;

/**
 * Test controller intentionally misused to simulate internal errors.
 */
class FaultyErrorController
{
    /**
     * NotFound method that throws an exception to simulate a failure.
     */
    public function notFound(): void
    {
        throw new Exception('Erreur simulée');
    }

    /**
     * ServerError method that throws an exception to simulate a failure.
     */
    public function serverError(): void
    {
        throw new Exception('Erreur simulée');
    }
}
