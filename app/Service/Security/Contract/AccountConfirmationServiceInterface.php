<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

interface AccountConfirmationServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function confirm(string $token): array;
}
