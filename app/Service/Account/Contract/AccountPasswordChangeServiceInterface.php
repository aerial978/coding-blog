<?php

declare(strict_types=1);

namespace App\Service\Account\Contract;

interface AccountPasswordChangeServiceInterface
{
    /**
     * Changes the authenticated user's password.
     *
     * @param array<string, mixed> $formData
     *
     * @return array{ok: true}|array{errors: array<string, string>}
     */
    public function changePassword(array $formData): array;
}
