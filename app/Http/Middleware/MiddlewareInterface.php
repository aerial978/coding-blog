<?php

namespace App\Http\Middleware;

use App\Http\Request;

interface MiddlewareInterface
{
    /**
     * Traite la requête avant qu’elle atteigne le contrôleur.
     *
     * @return bool Retourne false pour stopper la requête (le middleware a géré la réponse).
     */
    public function handle(Request $request, string $uri, string $method): bool;
}
