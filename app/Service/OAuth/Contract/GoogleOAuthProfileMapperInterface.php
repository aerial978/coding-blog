<?php

declare(strict_types=1);

namespace App\Service\OAuth\Contract;

interface GoogleOAuthProfileMapperInterface
{
    /**
     * @param array<string,mixed> $data
     *
     * @return array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * }
     */
    public function map(array $data): array;
}
