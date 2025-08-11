<?php

namespace App\Model\Entity;

/**
 * Represents a User entity.
 *
 * Extends the AbstractEntity class, allowing it to be hydrated from an array.
 * Stores basic user information such as ID, email, and creation date.
 */
class UserEntity extends AbstractEntity
{
    /** @var int|null Unique identifier for the user */
    private ?int $userId = null;

    /** @var string|null User's email address */
    private ?string $email = null;

    /** @var string|null Account creation timestamp */
    private ?string $createdAt = null;

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
}
