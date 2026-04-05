<?php

declare(strict_types=1);

namespace Tests\Double;

use App\Core\Contract\SessionInterface;

final class SessionFake implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function regenerateAndDeleteOld(): void
    {
        // Fake volontairement vide pour les tests unitaires.
    }

    public function regenerateKeepOld(): void
    {
        // Fake volontairement vide pour les tests unitaires.
    }
}
