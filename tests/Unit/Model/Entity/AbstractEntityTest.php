<?php

namespace Tests\Unit\Model\Entity;

use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Entity\DummyEntity;

/**
 * Unit tests for the AbstractEntity class (via DummyEntity).
 *
 * This test ensures that the `hydrate` method correctly assigns values
 * to entity properties and gracefully handles type errors without
 * interrupting the hydration process.
 */
final class AbstractEntityTest extends TestCase
{
    /**
     * Test that `hydrate` catches TypeError and continues processing.
     *
     * This verifies that when a property receives an invalid type,
     * the error is caught and logged, and the remaining valid properties
     * are still assigned correctly.
     */
    public function testHydrateCatchesTypeError(): void
    {
        $entity = new DummyEntity();

        // Attempt to hydrate with an invalid type for 'age'
        $hydrated = $entity->hydrate([
            'age'  => 'not_a_number',
            'name' => 'Test Name',
        ]);

        // Ensure that the entity is returned
        $this->assertInstanceOf(DummyEntity::class, $hydrated);

        // 'name' should be set correctly
        $this->assertSame('Test Name', $hydrated->getName());

        // 'age' should remain null due to the type error
        $this->assertNull($hydrated->getAge());
    }
}
