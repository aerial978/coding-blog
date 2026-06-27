<?php

declare(strict_types=1);

namespace App\Service\OAuth\Contract;

use App\Model\Entity\UserEntity;

interface OAuthUserProvisioningServiceInterface
{
    /**
     * Creates a local active user account from a verified OAuth profile.
     *
     * This method is used when an OAuth provider returns a verified email
     * but no matching local user account exists yet.
     *
     * @param array{
     *     id: string,
     *     email: string,
     *     email_verified: bool,
     *     name: string,
     *     avatar: string|null
     * } $profile
     *
     * @return UserEntity|null
     *     The created user entity, or null if creation failed.
     */
    public function provisionFromGoogleProfile(array $profile): ?UserEntity;
}
