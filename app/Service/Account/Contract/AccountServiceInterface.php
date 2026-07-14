<?php

declare(strict_types=1);

namespace App\Service\Account\Contract;

use App\Model\Entity\UserEntity;

interface AccountServiceInterface
{
    /**
     * Returns the currently authenticated user account.
     *
     * If no authenticated user identifier is available, or if the user
     * cannot be found in persistence, null is returned.
     */
    public function getCurrentUser(): ?UserEntity;
}
