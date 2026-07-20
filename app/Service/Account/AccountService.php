<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Model\Contract\UserModelInterface;
use App\Model\Entity\UserEntity;
use App\Security\Contract\AuthCheckerInterface;
use App\Service\Account\Contract\AccountServiceInterface;

final class AccountService implements AccountServiceInterface
{
    public function __construct(
        private AuthCheckerInterface $authChecker,
        private UserModelInterface $userModel,
    ) {
    }

    public function getCurrentUser(): ?UserEntity
    {
        $userId = $this->authChecker->getUserId();

        if ($userId === null) {
            return null;
        }

        return $this->userModel->findOneById($userId);
    }
}
