<?php

declare(strict_types=1);

namespace App\Core\Contract;

interface SqlHelperInterface
{
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollBack(): void;
}
