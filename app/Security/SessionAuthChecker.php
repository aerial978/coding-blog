<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Contract\SessionInterface;
use App\Security\Contract\AuthCheckerInterface;

/**
 * Vérifie l'état d'authentification en s'appuyant sur la session.
 *
 * Stockage attendu dans la session :
 * $_SESSION['user'] = [
 *     'id' => int,
 *     'roles' => ['USER', 'ADMIN'],
 * ];
 */
final class SessionAuthChecker implements AuthCheckerInterface
{
    public function __construct(private SessionInterface $session)
    {
    }

    // @SuppressWarnings(PHPMD.UnusedFormalParameter)
    public function isAuthenticated(): bool
    {
        $user = $this->session->get('user');
        return is_array($user) && isset($user['id']);
    }

    /** @return list<string> */
    // @SuppressWarnings(PHPMD.UnusedFormalParameter)
    public function getRoles(): array
    {
        $user = $this->session->get('user');
        if (!is_array($user)) {
            return [];
        }

        $roles = $user['roles'] ?? [];
        if (!is_array($roles)) {
            return [];
        }

        // Ne renvoyer que des chaînes
        $out = [];
        foreach ($roles as $r) {
            if (is_string($r)) {
                $out[] = $r;
            }
        }
        return $out;
    }

    public function getUserId(): ?int
    {
        $user = $this->session->get('user');
        return (is_array($user) && isset($user['id']) && is_int($user['id']))
            ? $user['id']
            : null;
    }
}
