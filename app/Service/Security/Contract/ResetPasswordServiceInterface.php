<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

interface ResetPasswordServiceInterface
{
    /**
     * @return array<string,mixed>
     *  - [] en cas de succès
     *  - ['error' => ErrorCode::...] en cas d'erreur simple
     *  - ['errors' => array<string,string>] en cas d'erreurs de validation
     */
    public function resetPassword(string $token, string $password, string $confirm): array;

    /**
     * Valide le token de reset-password.
     *
     * @return array<string,mixed>
     *  - ['ok' => true, 'context' => array<string,mixed>, 'hash' => string]
     *  - ['ok' => false, 'error' => string]
     */
    public function validateResetToken(string $token): array;
}
