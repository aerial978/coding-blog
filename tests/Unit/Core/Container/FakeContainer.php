<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use Psr\Container\ContainerInterface;
use RuntimeException;

final class FakeContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $entries = [];
    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new RuntimeException("Service non enregistré: {$id}");
        }

        $entry = $this->entries[$id];
        // Résolution lazy des closures/factories avec injection du conteneur
        if (\is_callable($entry)) {
            $instance = $entry($this);
            if ($instance === null) {
                throw new RuntimeException("Factory '{$id}' a retourné null.");
            }
            // memoize
            $this->entries[$id] = $instance;
            return $instance;
        }

        return $entry;
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->entries);
    }
}
