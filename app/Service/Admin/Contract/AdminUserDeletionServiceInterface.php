<?php

declare(strict_types=1);

namespace App\Service\Admin\Contract;

interface AdminUserDeletionServiceInterface
{
    /**
     * Permanently deletes a user account when permitted.
     *
     * @return array{errors?: list<string>}
     */
    public function delete(int $userId): array;
}
