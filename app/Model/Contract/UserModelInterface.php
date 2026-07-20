<?php

declare(strict_types=1);

namespace App\Model\Contract;

use App\Model\Entity\UserEntity;

interface UserModelInterface
{
    /**
     * @return list<UserEntity>
     */
    public function findAll(): array;

    /**
     * Utilisé pour l'unicité à l'inscription (peut retourner partiel).
     */
    public function findOneByUsername(string $username): ?UserEntity;

    /**
     * Finds a single user by its slug.
     */
    public function findOneBySlug(string $slug): ?UserEntity;

    /**
     * Utilisé pour l'unicité et certains flux (peut retourner complet).
     */
    public function findOneByEmail(string $email): ?UserEntity;

    /**
     * Retrieves a user by its unique identifier.
     *
     * Used notably for authenticated flows such as
     * email 2FA resend and post-login verification.
     *
     * @param int $userId
     *
     * @return UserEntity|null
     */
    public function findOneById(int $userId): ?UserEntity;

    /**
     * Login : retourne les champs nécessaires à l'auth (id, password, status, ...).
     */
    public function findAuthByUsername(string $username): ?UserEntity;

    /**
     * Login : retourne les champs nécessaires à l'auth (id, password, status, ...).
     */
    public function findAuthByEmail(string $email): ?UserEntity;

    /** Retourne l'ID créé (>0) ou <=0 en cas d'échec */
    public function createUser(UserEntity $user): int;

    /**
     * Crée automatiquement un utilisateur à partir d'un fournisseur OAuth.
     *
     * Le compte est créé actif et pourra ensuite être lié à une entrée
     * dans la table user_oauth_account.
     *
     * Retourne l'ID créé (>0) ou <=0 en cas d'échec.
     */
    public function createOAuthUser(UserEntity $user): int;
}
