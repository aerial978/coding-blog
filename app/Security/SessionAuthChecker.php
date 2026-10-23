<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Contract\SessionInterface;
use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;

/**
 * Vérifie l'authentification à partir de la session et de la base de données.
 *
 * L'utilisateur est chargé une seule fois par instance.
 */
final class SessionAuthChecker implements AuthCheckerInterface
{
    private bool $resolved = false;

    private ?UserEntity $authenticatedUser = null;

    public function __construct(
        private SessionInterface $session,
        private UserModelInterface $userModel
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->resolveUser() !== null;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $role = $this->resolveUser()?->getRole();

        return is_string($role) && $role !== '' ? [$role] : [];
    }

    public function getUserId(): ?int
    {
        return $this->resolveUser()?->getUserId();
    }

    private function resolveUser(): ?UserEntity
    {
        if ($this->resolved) {
            return $this->authenticatedUser;
        }

        $this->resolved = true;

        $sessionUser = $this->session->get('user');

        if (!$this->hasValidSessionUser($sessionUser)) {
            return null;
        }

        try {
            $user = $this->userModel->findOneById($sessionUser['id']);
        } catch (\Throwable $exception) {
            // En cas d'erreur SQL, refuser l'accès sans détruire la session.
            return null;
        }

        if ($user === null || $user->getStatus() !== 'active') {
            // Le compte n'existe plus ou n'est plus autorisé à se connecter.
            $this->session->remove('user');

            return null;
        }

        $this->authenticatedUser = $user;

        return $this->authenticatedUser;
    }

    /**
     * @phpstan-assert-if-true array{id: int} $sessionUser
     */
    private function hasValidSessionUser(mixed $sessionUser): bool
    {
        return is_array($sessionUser)
            && isset($sessionUser['id'])
            && is_int($sessionUser['id'])
            && $sessionUser['id'] > 0;
    }
}
