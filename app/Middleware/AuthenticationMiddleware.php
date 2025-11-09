<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Contract\FlashInterface;
use App\Core\Logger;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Security\Contract\AuthCheckerInterface;

/**
 * Middleware d’authentification.
 *
 * Vérifie qu’un utilisateur est connecté avant d’accéder à certaines routes.
 */
final class AuthenticationMiddleware implements MiddlewareInterface
{
    /** @var string[] Liste des routes protégées */
    private const PROTECTED_ROUTES = [
        '/change-password',
        '/login',
    ];

    public function __construct(
        private AuthCheckerInterface $authChecker,
        private FlashInterface $flash
    ) {
    }

    public function handle(Request $request, string $uri, string $method): bool
    {
        Logger::getLogger('app')->info('auth_mw_entry', [
            'uri'    => $uri,
            'method' => $method,
        ]);

        if (!in_array($uri, self::PROTECTED_ROUTES, true)) {
            return true; // Route publique → OK
        }

        if ($this->authChecker->isAuthenticated($request)) {
            return true; // Authentifié → OK
        }

        // Bloqué : utilisateur non connecté
        $this->flash->add('error', 'Vous devez être connecté pour accéder à cette page.');
        Logger::getLogger('app')->warning('auth_mw_block', ['uri' => $uri]);

        header('Location: /login', true, 302);
        return false;
    }
}
