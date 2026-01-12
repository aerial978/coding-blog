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
        // On ajoute (ou renforce) quelques headers de sécurité.
        // Ici on ne s’embête pas à vérifier s’ils existent déjà, ce n’est pas gênant
        // dans un petit projet portfolio.

        // Empêche l’inclusion dans des iframes externes
        header('X-Frame-Options: SAMEORIGIN');

        // Évite certaines attaques de type MIME sniffing
        header('X-Content-Type-Options: nosniff');

        // Politique de referrer raisonnable
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Tu pourrais en ajouter d’autres plus tard (CSP, etc.)

        // On laisse continuer la chaîne de middlewares / contrôleur
        return true;
    }
}
