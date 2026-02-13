<?php

declare(strict_types=1);

namespace App\Service\Security\Contract;

interface LoginServiceInterface
{
    /**
     * Attempts to authenticate a user.
     *
     * @param array<string, mixed> $credentials
     *
     * @return array{
     *   ok?: bool,
     *   error?: string,
     *   errors?: list<string>,
     *   old?: array<string, mixed>
     * }
     */
    public function login(array $credentials): array;
}
