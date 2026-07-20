<?php

declare(strict_types=1);

namespace App\Security\Contract;

interface TurnstileValidatorInterface
{
    public function validate(string $token, ?string $ip = null): bool;

    /**
     * @return array<string,mixed>
     */
    public function getLastResponse(): ?array;
}
