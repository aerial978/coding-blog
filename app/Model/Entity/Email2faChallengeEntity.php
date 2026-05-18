<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * Represents a temporary email 2FA challenge.
 *
 * This entity maps the `user_2fa_challenge` table and stores the
 * short-lived verification challenge used during the second
 * authentication step after successful password validation.
 *
 * It contains only temporary authentication data:
 * - generated verification code hash
 * - expiration time
 * - usage state
 * - number of verification attempts
 * - resend tracking
 */
class Email2faChallengeEntity extends AbstractEntity
{
    /**
     * Unique identifier of the challenge.
     */
    private ?int $id = null;

    /**
     * Related user identifier.
     */
    private ?int $userId = null;

    /**
     * Secure hashed version of the email 2FA code.
     */
    private ?string $codeHash = null;

    /**
     * Expiration datetime of the challenge.
     */
    private ?string $expiresAt = null;

    /**
     * Indicates whether the code has already been used.
     */
    private bool $used = false;

    /**
     * Datetime when the code was used.
     */
    private ?string $usedAt = null;

    /**
     * Number of verification attempts.
     */
    private int $attempts = 0;

    /**
     * Datetime when the challenge was created.
     */
    private ?string $createdAt = null;

    /**
     * Datetime of the last email sending.
     */
    private ?string $lastSentAt = null;

    /**
     * Gets the challenge ID.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Sets the challenge ID.
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets the related user ID.
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Sets the related user ID.
     */
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Gets the hashed verification code.
     */
    public function getCodeHash(): ?string
    {
        return $this->codeHash;
    }

    /**
     * Sets the hashed verification code.
     */
    public function setCodeHash(string $codeHash): self
    {
        $this->codeHash = $codeHash;

        return $this;
    }

    /**
     * Gets the expiration datetime.
     */
    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    /**
     * Sets the expiration datetime.
     */
    public function setExpiresAt(string $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * Checks whether the challenge has already been used.
     */
    public function isUsed(): bool
    {
        return $this->used;
    }

    /**
     * Sets the used state.
     */
    public function setUsed(bool $used): self
    {
        $this->used = $used;

        return $this;
    }

    /**
     * Gets the usage datetime.
     */
    public function getUsedAt(): ?string
    {
        return $this->usedAt;
    }

    /**
     * Sets the usage datetime.
     */
    public function setUsedAt(?string $usedAt): self
    {
        $this->usedAt = $usedAt;

        return $this;
    }

    /**
     * Gets the number of verification attempts.
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Sets the number of verification attempts.
     */
    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;

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
     * Gets the last sent datetime.
     */
    public function getLastSentAt(): ?string
    {
        return $this->lastSentAt;
    }

    /**
     * Sets the last sent datetime.
     */
    public function setLastSentAt(string $lastSentAt): self
    {
        $this->lastSentAt = $lastSentAt;

        return $this;
    }
}