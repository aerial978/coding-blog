<?php

namespace App\Model\Entity;

/**
 * Base abstract entity class.
 *
 * Provides a common `hydrate` method for populating entity properties
 * using an associative array of data. This is useful for initializing
 * objects from database results or other structured arrays.
 */
abstract class AbstractEntity
{
    /**
     * Populates the entity's properties from an associative array.
     *
     * For each key in the provided `$data` array:
     * - Converts the key to a setter method name (e.g. `first_name` → `setFirstName`).
     * - If the setter exists in the entity, it is called with the corresponding value.
     * - Type errors during assignment are caught and logged without interrupting hydration.
     *
     * @param array<string, mixed> $data Associative array of property names and values.
     * @return static Returns the current instance for method chaining.
     */
    public function hydrate(array $data): static
    {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));

            if (method_exists($this, $method)) {
                try {
                    $this->$method($value);
                } catch (\TypeError $e) {
                    error_log('Hydration type error in ' . static::class . " on method $method: " . $e->getMessage());
                    continue;
                }
            }
        }

        return $this;
    }
}
