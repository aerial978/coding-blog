<?php

namespace Tests\Fixtures\Entity;

use App\Model\Entity\AbstractEntity;

/**
 * DummyEntity is a test fixture class used to simulate
 * a simple entity with "age" and "name" properties.
 *
 * This class extends AbstractEntity to allow hydration
 * from an associative array and is mainly used for
 * unit testing purposes.
 */
class DummyEntity extends AbstractEntity
{
    private ?int $age     = null;
    private ?string $name = null;

    /**
     * Set the age of the entity.
     *
     * @param int $age
     */
    public function setAge(int $age): void
    {
        $this->age = $age;
    }

    /**
     * Get the age of the entity.
     *
     * @return int|null
     */
    public function getAge(): ?int
    {
        return $this->age;
    }

    /**
     * Set the name of the entity.
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get the name of the entity.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }
}
