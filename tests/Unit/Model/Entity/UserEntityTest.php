<?php

namespace Tests\Unit\Model\Entity;

use App\Model\Entity\UserEntity;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the UserEntity class.
 *
 * These tests verify that the `hydrate` method correctly sets
 * the entity's properties from an associative array.
 */
final class UserEntityTest extends TestCase
{
    /**
     * Test that `hydrate` correctly assigns all provided properties.
     *
     * This ensures that the data passed in an associative array
     * is properly mapped to the corresponding class properties
     * using the entity's setters.
     */
    public function testHydrateSetsProperties(): void
    {
        $user = new UserEntity();

        // Hydrate the entity with test data
        $user->hydrate([
            'user_id'    => 1,
            'email'      => 'test@example.com',
            'created_at' => '2024-01-01'
        ]);

        // Validate that each property has been set correctly
        $this->assertEquals(1, $user->getUserId());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('2024-01-01', $user->getCreatedAt());
    }
}
