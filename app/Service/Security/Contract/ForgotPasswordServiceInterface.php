<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

interface ForgotPasswordServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function requestReset(string $identifier): array;
}
