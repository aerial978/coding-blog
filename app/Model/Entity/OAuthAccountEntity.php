<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * Represents an OAuth account linked to a local user.
 *
 * This entity maps the `user_oauth_account` table and stores the
 * external identity information returned by an OAuth provider such
 * as Google.
 *
 * It allows the application to link one or several external providers
 * to a local user account without coupling the `user` table to a
 * specific provider.
 */
class OAuthAccountEntity extends AbstractEntity
{
    /**
     * Unique identifier of the OAuth account link.
     */
    private ?int $id = null;

    /**
     * Related local user identifier.
     */
    private ?int $userId = null;

    /**
     * OAuth provider name.
     *
     * Example values: "google", "github", "microsoft".
     */
    private ?string $provider = null;

    /**
     * Unique user identifier returned by the OAuth provider.
     *
     * For Google OpenID Connect, this corresponds to the "sub" claim.
     */
    private ?string $providerUserId = null;

    /**
     * Email address returned by the OAuth provider.
     */
    private ?string $email = null;

    /**
     * Indicates whether the provider confirmed the email ownership.
     */
    private bool $emailVerified = false;

    /**
     * Datetime when the OAuth account link was created.
     */
    private ?string $createdAt = null;

    /**
     * Datetime when the OAuth account link was last updated.
     */
    private ?string $updatedAt = null;

    /**
     * Gets the OAuth account link ID.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Sets the OAuth account link ID.
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets the related local user ID.
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Sets the related local user ID.
     */
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Gets the OAuth provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Sets the OAuth provider name.
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Gets the provider user ID.
     */
    public function getProviderUserId(): ?string
    {
        return $this->providerUserId;
    }

    /**
     * Sets the provider user ID.
     */
    public function setProviderUserId(string $providerUserId): self
    {
        $this->providerUserId = $providerUserId;

        return $this;
    }

    /**
     * Gets the email address returned by the provider.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Sets the email address returned by the provider.
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Checks whether the OAuth email is verified.
     */
    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    /**
     * Sets whether the OAuth email is verified.
     */
    public function setEmailVerified(bool $emailVerified): self
    {
        $this->emailVerified = $emailVerified;

        return $this;
    }

    /**
     * Gets the creation datetime.
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * Sets the creation datetime.
     */
    public function setCreatedAt(string $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Gets the last update datetime.
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * Sets the last update datetime.
     */
    public function setUpdatedAt(string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
