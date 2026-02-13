<?php

declare(strict_types=1);

namespace App\Model\Contract;

use App\Model\Entity\UserEntity;

interface UserModelInterface
{
    /**
     * Utilisé pour l'unicité à l'inscription (peut retourner partiel).
     */
    public function findOneByUsername(string $username): ?UserEntity;

    /**
     * Utilisé pour l'unicité et certains flux (peut retourner complet).
     */
    public function findOneByEmail(string $email): ?UserEntity;

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
}
