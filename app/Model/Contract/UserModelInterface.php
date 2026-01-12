<?php

declare(strict_types=1);

namespace App\Model\Contract;

use App\Model\Entity\UserEntity;

interface UserModelInterface
{
    public function findOneByUsername(string $username): mixed;
    public function findOneByEmail(string $email): mixed;

    /** Retourne l'ID créé (>0) ou <=0 en cas d'échec */
    public function createUser(UserEntity $user): int;
}
