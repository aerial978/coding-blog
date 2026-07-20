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
    *   ok?: true,
    *   two_factor_required?: true,
    *   remember_me_token?: string,
    *   errors?: list<string|int>,
    *   old?: array{identifier:string, remember_me?: string}
    * }
    */
    public function login(array $credentials): array;
}
