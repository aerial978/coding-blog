<?php

declare(strict_types=1);

namespace App\Support;

final class ErrorListNormalizer
{
    /** @return list<string> */
    public function normalize(mixed $errors): array
    {
        if (!is_array($errors)) {
            return [];
        }

        $out = [];
        foreach ($errors as $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $out[] = (string) $value;
            }
        }

        return $out;
    }
}
