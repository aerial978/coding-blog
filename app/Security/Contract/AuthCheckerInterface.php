<?php

declare(strict_types=1);

namespace App\Security\Contract;

use App\Http\Request;

/**
 * Interface d’abstraction pour vérifier l’état d’authentification
 * d’un utilisateur (quelle que soit la méthode : session, token, cookie...).
 */
interface AuthCheckerInterface
{
    /**
     * Vérifie si un utilisateur est connecté (authentifié).
     */
    public function isAuthenticated(Request $request): bool;

    /**
     * Retourne les rôles de l’utilisateur connecté.
     *
     * @return string[] Liste des rôles (ex: ['USER', 'ADMIN'])
     */
    public function getRoles(Request $request): array;

    /**
     * Retourne l’identifiant de l’utilisateur si disponible.
     */
    public function getUserId(Request $request): ?int;
}
