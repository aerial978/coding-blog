<?php

declare(strict_types=1);

namespace App\Http\Contract;

interface ResponderInterface
{
    /**
     * @param array<string,mixed> $data
     */
    public function render(string $template, array $data = []): void;

    public function redirect(string $path): void;
}
