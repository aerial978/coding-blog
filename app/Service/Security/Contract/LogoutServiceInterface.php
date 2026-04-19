<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

interface LogoutServiceInterface
{
    public function logout(): void;
}
