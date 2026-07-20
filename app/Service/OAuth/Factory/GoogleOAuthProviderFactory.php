<?php

declare(strict_types=1);

namespace App\Service\OAuth\Factory;

use League\OAuth2\Client\Provider\Google;

final class GoogleOAuthProviderFactory
{
    public function create(): Google
    {
        return new Google([
            'clientId'     => $this->envString('GOOGLE_CLIENT_ID'),
            'clientSecret' => $this->envString('GOOGLE_CLIENT_SECRET'),
            'redirectUri'  => $this->envString('GOOGLE_REDIRECT_URI'),
            'scopes'       => $this->envScopes('GOOGLE_OAUTH_SCOPES'),
        ]);
    }

    private function envString(string $key): string
    {
        $value = $_ENV[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<string>
     */
    private function envScopes(string $key): array
    {
        $value = $this->envString($key);

        if ($value === '') {
            return ['openid', 'email', 'profile'];
        }

        /** @var list<string> $scopes */
        $scopes = array_values(array_filter(
            preg_split('/\s+/', $value) ?: [],
            static fn (string $scope): bool => $scope !== ''
        ));

        return $scopes;
    }
}
