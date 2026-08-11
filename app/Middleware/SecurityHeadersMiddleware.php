<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;

/**
 * Middleware d’ajout d’en-têtes de sécurité HTTP.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function handle(Request $request, string $uri, string $method): bool
    {
        // Empêche l’inclusion dans des iframes externes
        header('X-Frame-Options: SAMEORIGIN');

        // Évite certaines attaques de type MIME sniffing
        header('X-Content-Type-Options: nosniff');

        // Politique de referrer raisonnable
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Désactive des fonctionnalités navigateur non utilisées
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // Tu pourrais en ajouter d’autres plus tard (CSP, etc.)

        // On laisse continuer la chaîne de middlewares / contrôleur
        return true;
    }
}
