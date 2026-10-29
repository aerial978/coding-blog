<?php

declare(strict_types=1);

namespace App\Service\Account\Contract;

interface AccountUsernameUpdateServiceInterface
{
    /**
     * Updates the authenticated user's username.
     *
     * @param array<string, mixed> $form
     * @return array{ok: true}|array{errors: array<string, string>}
     */
    public function update(array $form): array;
}
