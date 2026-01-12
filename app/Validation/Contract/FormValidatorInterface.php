<?php

declare(strict_types=1);

namespace App\Validation\Contract;

interface FormValidatorInterface
{
    /**
     * @param array<string,mixed> $data
     * @return list<string>
     */
    public function validateRegistration(array $data): array;

    /**
     * @return string|null  Code d’erreur ou null si OK
     */
    public function validateEmailField(string $email): ?string;
}
