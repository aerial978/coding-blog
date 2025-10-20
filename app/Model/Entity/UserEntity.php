<?php

namespace App\Model\Entity;

/**
 * Represents a User entity.
 *
 * This class extends the AbstractEntity base class, allowing automatic
 * hydration from arrays. It encapsulates core user attributes such as
 * identifiers, credentials, and timestamps.
 *
 * The entity follows a strict encapsulation principle and provides
 * typed getter and setter methods for all user properties.
 */
class UserEntity extends AbstractEntity
{
    /** @var int|null Unique identifier for the user */
    private ?int $userId = null;

    /** @var string|null User's username */
    private ?string $username = null;

    /** @var string|null User's slug */
    private ?string $slug = null;

    /** @var string|null User's email address */
    private ?string $email = null;

    private ?string $password = null;

    /** @var string|null Account creation timestamp */
    private ?string $createdAt = null;

    private ?string $status = null;

    /**
     * Gets the user ID.
     *
     * @return int|null The user's ID, or null if not set.
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Sets the user ID.
     *
     * @param int $userId The user's unique identifier.
     * @return self
     */
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Gets the user's username.
     *
     * @return string|null The username, or null if not set.
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Sets the user's username.
     *
     * @param string $username The username.
     * @return self
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Gets the user's slug.
     *
     * Typically used for URL generation or SEO-friendly identifiers.
     *
     * @return string|null The slug value, or null if not set.
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * Sets the user's slug.
     *
     * @param string $slug The slug derived from the username.
     * @return self
     */
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * Gets the user's email address.
     *
     * @return string|null The email address, or null if not set.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Sets the user's email address.
     *
     * @param string $email The email address.
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Gets the user's hashed password.
     *
     * @return string|null The hashed password, or null if not set.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Sets the user's hashed password.
     *
     * @param string $password The hashed password.
     * @return self
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Gets the account creation date.
     *
     * @return string|null The creation date, or null if not set.
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * Sets the account creation date.
     *
     * @param string $createdAt The date the account was created.
     * @return self
     */
    public function setCreatedAt(string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Gets the user's account status.
     *
     * Example values: "active", "pending", "banned", etc.
     *
     * @return string|null The account status, or null if not set.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Sets the user's account status.
     *
     * @param string $status The new status value.
     * @return self
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }
}
