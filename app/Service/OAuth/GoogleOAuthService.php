<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Service\OAuth\Contract\GoogleOAuthProviderInterface;
use App\Service\OAuth\Contract\GoogleOAuthServiceInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

final class GoogleOAuthService implements GoogleOAuthServiceInterface
{
    public function __construct(private GoogleOAuthProviderInterface $provider)
    {
    }

    public function getAuthorizationUrl(): string
    {
        return $this->provider->getAuthorizationUrl();
    }

    public function getState(): string
    {
        return $this->provider->getState();
    }

    public function getAccessToken(string $code): AccessTokenInterface
    {
        return $this->provider->getAccessToken($code);
    }

    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * }
     */
    public function getUserProfile(AccessTokenInterface $accessToken): array
    {
        $data = $this->provider->getResourceOwnerData($accessToken);

        return [
            'id'             => $this->stringValue($data['sub'] ?? $data['id'] ?? null),
            'email'          => $this->stringValue($data['email'] ?? null),
            'email_verified' => $this->boolValue($data['email_verified'] ?? null),
            'name'           => $this->stringValue($data['name'] ?? null),
            'avatar'         => $this->nullableStringValue($data['picture'] ?? null),
        ];
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
