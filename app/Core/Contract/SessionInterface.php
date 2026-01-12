<?php

declare(strict_types=1);

namespace App\Core\Contract;

interface SessionInterface
{
    public function set(string $key, mixed $value): void;

    public function get(string $key, mixed $default = null): mixed;

    public function remove(string $key): void;
}
