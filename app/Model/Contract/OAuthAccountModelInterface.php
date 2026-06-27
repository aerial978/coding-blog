<?php

declare(strict_types=1);

namespace App\Model\Contract;

use App\Model\Entity\OAuthAccountEntity;

interface OAuthAccountModelInterface
{
    /**
     * Finds an OAuth account link by provider and provider user identifier.
     *
     * This method is used during OAuth login to determine whether the
     * external provider account is already linked to a local user.
     */
    public function findByProviderAndProviderUserId(string $provider, string $providerUserId): ?OAuthAccountEntity;

    /**
    * Finds an OAuth account link by provider and local user identifier.
    *
    * This method is used to determine whether a local user account
    * already has an OAuth link for a given provider.
    */
    public function findByProviderAndUserId(string $provider, int $userId): ?OAuthAccountEntity;

    /**
     * Creates a new OAuth account link and returns its generated identifier.
     *
     * This method is used when a user signs in with an OAuth provider
     * for the first time and the external account must be linked to
     * a local application user.
     */
    public function create(OAuthAccountEntity $account): int;
}
