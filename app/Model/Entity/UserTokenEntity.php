<?php

namespace App\Model\Entity;

/**
 * Represents a User Token entity.
 *
 * This entity maps to the `user_token` table and is responsible for
 * storing temporary tokens used for various account-related actions,
 * such as email confirmation, password reset, or two-factor authentication.
 *
 * Each token is associated with a user, includes an expiration time,
 * and tracks whether it has been used.
 */
class UserTokenEntity extends AbstractEntity
{
    /** @var int|null Unique identifier of the token record */
    private ?int $id = null;

    /** @var int|null Identifier of the user associated with the token */
    private ?int $userId = null;

    /** @var string|null Type of token (e.g. 'confirm_email', 'reset_password') */
    private ?string $type = null;

    /** @var string|null Hashed version of the token value */
    private ?string $tokenHash = null;

    /** @var string|null Expiration timestamp of the token */
    private ?string $expiresAt = null;

    /** @var bool|null Indicates whether the token has been used */
    private ?bool $used = null;

    /** @var string|null Timestamp when the token was used */
    private ?string $usedAt = null;

    /** @var string|null Creation timestamp of the token */
    private ?string $createdAt = null;

    /**
     * Gets the token's unique identifier.
     *
     * @return int|null The token ID, or null if not set.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Sets the token's unique identifier.
     *
     * @param int $id The token ID.
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Gets the ID of the user associated with the token.
     *
     * @return int|null The user ID, or null if not set.
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Sets the user ID associated with the token.
     *
     * @param int $userId The user ID.
     * @return self
     */
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Gets the token type.
     *
     * @return string|null The token type (e.g. 'confirm_email', 'reset_password').
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Sets the token type.
     *
     * @param string $type The token type.
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Gets the hashed token value.
     *
     * @return string|null The token hash, or null if not set.
     */
    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    /**
     * Sets the hashed token value.
     *
     * @param string $tokenHash The token hash.
     * @return self
     */
    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;
        return $this;
    }

    /**
     * Gets the expiration timestamp of the token.
     *
     * @return string|null The expiration date and time.
     */
    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    /**
     * Sets the expiration timestamp of the token.
     *
     * @param string $expiresAt The expiration date and time.
     * @return self
     */
    public function setExpiresAt(string $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Checks whether the token has been used.
     *
     * @return bool|null True if used, false if not, or null if undefined.
     */
    public function isUsed(): ?bool
    {
        return $this->used;
    }

    /**
     * Marks the token as used or unused.
     *
     * @param bool $used True if the token has been used.
     * @return self
     */
    public function setUsed(bool $used): self
    {
        $this->used = $used;
        return $this;
    }

    /**
     * Gets the timestamp when the token was used.
     *
     * @return string|null The usage timestamp, or null if not applicable.
     */
    public function getUsedAt(): ?string
    {
        return $this->usedAt;
    }

    /**
     * Sets the timestamp when the token was used.
     *
     * @param string|null $usedAt The date/time the token was consumed.
     * @return self
     */
    public function setUsedAt(?string $usedAt): self
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    /**
     * Gets the creation timestamp of the token.
     *
     * @return string|null The creation date and time.
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * Sets the creation timestamp of the token.
     *
     * @param string $createdAt The date/time the token was created.
     * @return self
     */
    public function setCreatedAt(string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
