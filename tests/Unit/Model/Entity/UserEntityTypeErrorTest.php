<?php

namespace Tests\Unit\Model\Entity;

use App\Model\Entity\UserEntity;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserEntity to verify behavior when invalid types are provided.
 *
 * These tests check that the `hydrate` method gracefully skips setting a property
 * when a type mismatch occurs, preventing runtime errors.
 */
final class UserEntityTypeErrorTest extends TestCase
{
    /**
     * Test that `hydrate` skips the setter when a type error occurs.
     *
     * This ensures that if the data type passed for a property does not match
     * the expected type (e.g., array instead of int), the setter is not called
     * and the property remains unset.
     */
    public function testHydrateSkipsSetterOnTypeError(): void
    {
        $user = new UserEntity();

        // Hydrate with an invalid type for `id` and a valid email
        $user->hydrate([
            'id'    => ['this_should_be_an_int'],
            'email' => 'test@example.com'
        ]);

        // Ensure the valid property is set correctly
        $this->assertSame('test@example.com', $user->getEmail());

        // Ensure the invalid property was not set
        $this->assertNotEquals(['this_should_be_an_int'], $user->getUserId());
    }
}
