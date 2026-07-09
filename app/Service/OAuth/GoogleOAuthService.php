<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Service\OAuth\Contract\GoogleOAuthProfileMapperInterface;
use App\Service\OAuth\Contract\GoogleOAuthProviderInterface;
use App\Service\OAuth\Contract\GoogleOAuthServiceInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

final class GoogleOAuthService implements GoogleOAuthServiceInterface
{
    public function __construct(
        private GoogleOAuthProviderInterface $provider,
        private GoogleOAuthProfileMapperInterface $profileMapper,
    ) {
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
        return $this->profileMapper->map(
            $this->provider->getResourceOwnerData($accessToken)
        );
    }
}
