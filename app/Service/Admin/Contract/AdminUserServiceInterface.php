<?php

declare(strict_types=1);

namespace App\Service\Admin\Contract;

interface AdminUserServiceInterface
{
    /**
     * Updates fields editable from the admin user management interface.
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public function update(int $userId, array $form): array;
}
