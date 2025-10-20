<?php

namespace App\Core\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Minimal PSR-11 compliant dependency injection container.
 *
 * This container supports both pre-instantiated singletons and lazy-loaded
 * services defined as closures (factories). Each factory is invoked only once,
 * and its resulting instance is stored (singleton behavior).
 *
 * It provides a lightweight alternative to full-featured containers such as
 * Symfony's DependencyInjection component, while remaining PSR-11 compatible.
 */
final class AppContainer implements ContainerInterface
{
    /**
     * @var array<string, callable|object>
     *     Map of service identifiers to either objects or factory closures.
     */
    private array $entries;

    /**
     * Constructor.
     *
     * Initializes the container with an optional list of predefined entries.
     *
     * @param array<string, callable|object> $definitions
     *     Initial list of service definitions (objects or factories).
     */
    public function __construct(array $definitions = [])
    {
        $this->entries = $definitions;
    }

    /**
     * Registers or replaces a container entry.
     *
     * Allows dynamic service definition at runtime.
     *
     * @param string $id
     *     Service identifier (usually the class name or alias).
     * @param callable(ContainerInterface):object|object $entry
     *     Either a factory closure or a pre-instantiated service.
     */
    public function set(string $id, callable|object $entry): void
    {
        $this->entries[$id] = $entry;
    }

    /**
     * Retrieves a service instance from the container.
     *
     * If the entry is defined as a factory (callable), it will be executed once,
     * and its returned instance will be cached for future calls (lazy singleton).
     *
     * @param string $id
     *     Service identifier to retrieve.
     * @return mixed
     *
     * @throws NotFoundExceptionInterface
     *     If the requested service ID does not exist in the container.
     * @throws ContainerExceptionInterface
     *     If an error occurs while instantiating the service.
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class ("Service not found: $id") extends RuntimeException implements NotFoundExceptionInterface {
            };
        }

        /** @var (callable(ContainerInterface): object)|object $entry */
        $entry = $this->entries[$id];

        if (is_callable($entry)) {
            try {
                $instance = $entry($this);

                if (!is_object($instance)) {
                    // on refuse tout autre type qu'un objet
                    throw new RuntimeException("Factory for '$id' must return an object.");
                }

                // on mémorise uniquement l'objet, sans réassigner $entry
                $this->entries[$id] = $instance;

                return $instance;
            } catch (\Throwable $e) {
                throw new class ("Cannot create service '$id': " . $e->getMessage(), 0, $e) extends RuntimeException implements ContainerExceptionInterface {
                };
            }
        }

        return $entry;
    }

    /**
     * Checks whether a given service identifier exists in the container.
     *
     * @param string $id
     *     Service identifier.
     * @return bool
     *     True if the service is defined; false otherwise.
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
