<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Service\OAuth\Contract\GoogleOAuthProviderInterface;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;

final class GoogleOAuthProviderAdapter implements GoogleOAuthProviderInterface
{
    public function __construct(private Google $provider)
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
        return $this->provider->getAccessToken('authorization_code', [
            'code' => $code,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function getResourceOwnerData(AccessTokenInterface $accessToken): array
    {
        if (!$accessToken instanceof AccessToken) {
            throw new InvalidArgumentException('Invalid OAuth access token.');
        }

        $owner = $this->provider->getResourceOwner($accessToken);

        /** @var array<string,mixed> $data */
        $data = $owner->toArray();

        return $data;
    }
}
