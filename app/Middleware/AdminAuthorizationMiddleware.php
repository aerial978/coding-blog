<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Controller\ErrorController;
use App\Core\Logger;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Security\Contract\AuthCheckerInterface;

/**
 * Middleware d'autorisation de l'espace d'administration.
 *
 * Autorise uniquement les utilisateurs authentifiés possédant le rôle ADMIN.
 */
final class AdminAuthorizationMiddleware implements MiddlewareInterface
{
    private const ADMIN_PREFIX = '/admin';

    public function __construct(
        private AuthCheckerInterface $authChecker,
        private ErrorController $errorController
    ) {
    }

    public function handle(Request $request, string $uri, string $method): bool
    {
        if (!$this->isAdminRoute($uri)) {
            return true;
        }

        if (
            $this->authChecker->isAuthenticated()
            && in_array('ADMIN', $this->authChecker->getRoles(), true)
        ) {
            return true;
        }

        Logger::getLogger('app')->warning('admin_authorization_denied', [
            'uri'     => $uri,
            'method'  => $method,
            'user_id' => $this->authChecker->getUserId(),
        ]);

        $this->errorController->forbidden();

        return false;
    }

    private function isAdminRoute(string $uri): bool
    {
        return $uri === self::ADMIN_PREFIX
            || str_starts_with($uri, self::ADMIN_PREFIX . '/');
    }
}
