<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

interface ConfirmationResendServiceInterface
{
    /**
     * @return array<string,mixed>
     */
    public function resend(string $email): array;
}
