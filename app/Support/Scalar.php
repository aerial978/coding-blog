<?php

declare(strict_types=1);

namespace App\Support;

final class Scalar
{
    private function __construct()
    {
    }

    public static function toString(mixed $value): string
    {
        return (is_string($value) || is_int($value) || is_float($value)) ? (string) $value : '';
    }
}
