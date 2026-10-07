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
     * @param array<string,mixed> $data
     * @return list<string>
     */
    public function validateLogin(array $data): array;

    /**
     * @return string|null  Code d’erreur ou null si OK
     */
    public function validateEmailField(string $email): ?string;

    /**
     * Validation password réutilisable (reset-password, register, change-password…)
     *
     * @return string|null Code d’erreur ou null si OK
     */
    public function validatePasswordField(string $password): ?string;

    /**
     * Validates fields editable from the admin user management interface.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validateAdminUserEdit(array $data): array;
}
