<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Service\OAuth\Contract\GoogleOAuthProfileMapperInterface;

final class GoogleOAuthProfileMapper implements GoogleOAuthProfileMapperInterface
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
    public function map(array $data): array
    {
        return [
            'id'             => $this->profileId($data),
            'email'          => $this->stringValue($data['email'] ?? null),
            'email_verified' => $this->boolValue($data['email_verified'] ?? null),
            'name'           => $this->stringValue($data['name'] ?? null),
            'avatar'         => $this->nullableStringValue($data['picture'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function profileId(array $data): string
    {
        return $this->stringValue($data['sub'] ?? $data['id'] ?? null);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function nullableStringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function boolValue(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}
