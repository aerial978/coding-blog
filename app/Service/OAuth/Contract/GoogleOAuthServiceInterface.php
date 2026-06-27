<?php

declare(strict_types=1);

namespace App\Service\OAuth\Contract;

use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Defines the contract for Google OAuth authentication.
 *
 * This service is responsible for:
 * - generating the Google authorization URL;
 * - managing the OAuth state parameter;
 * - exchanging authorization codes for access tokens;
 * - retrieving authenticated Google user profiles.
 */
interface GoogleOAuthServiceInterface
{
    /**
     * Generates the Google authorization URL.
     *
     * The returned URL is used to redirect the user to Google's
     * authentication and consent screen.
     *
     * @return string
     *     Fully qualified Google authorization URL.
     */
    public function getAuthorizationUrl(): string;

    /**
     * Returns the OAuth state generated during authorization URL creation.
     *
     * This value must be stored in the session before redirecting
     * the user to Google and later compared during the callback
     * to protect against CSRF attacks.
     *
     * @return string
     *     Generated OAuth state value.
     */
    public function getState(): string;

    /**
     * Exchanges a Google authorization code for an access token.
     *
     * Google returns a temporary authorization code during
     * the OAuth callback. This method exchanges that code
     * for a usable access token.
     *
     * @param string $code
     *     Authorization code received from Google.
     *
     * @return AccessTokenInterface
     *     OAuth access token.
     */
    public function getAccessToken(string $code): AccessTokenInterface;

    /**
     * Retrieves the authenticated Google user profile.
     *
     * Returns a normalized user profile extracted from
     * Google's OAuth resource owner endpoint.
     *
     * @param AccessTokenInterface $accessToken
     *     Previously obtained OAuth access token.
     *
     * @return array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * }
     */
    public function getUserProfile(AccessTokenInterface $accessToken): array;
}
