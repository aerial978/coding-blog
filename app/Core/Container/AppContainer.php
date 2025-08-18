<?php
namespace App\Core\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Conteneur PSR-11 minimal (lazy singletons via closures).
 */
final class AppContainer implements ContainerInterface
{
    /** @var array<string, callable|object> */
    private array $entries;

    /** @param array<string, callable|object> $definitions */
    public function __construct(array $definitions = [])
    {
        $this->entries = $definitions;
    }

    /**
     * Enregistre/remplace une entrée.
     * @param callable(ContainerInterface):object|object $entry
     */
    public function set(string $id, callable|object $entry): void
    {
        $this->entries[$id] = $entry;
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class("Service not found: $id")
                extends RuntimeException implements NotFoundExceptionInterface {};
        }

        $entry = $this->entries[$id];

        // Lazy factory → on instancie une fois et on mémorise
        if (is_callable($entry)) {
            try {
                $instance = $entry($this);
                if ($instance === null) {
                    throw new RuntimeException("Factory for '$id' returned null.");
                }
                $this->entries[$id] = $entry = $instance;
            } catch (\Throwable $e) {
                throw new class("Cannot create service '$id': ".$e->getMessage(), 0, $e)
                    extends RuntimeException implements ContainerExceptionInterface {};
            }
        }

        return $entry;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
