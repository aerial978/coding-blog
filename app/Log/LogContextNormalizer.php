<?php

declare(strict_types=1);

namespace App\Log;

final class LogContextNormalizer
{
    /**
     * @param array<string,mixed> $context
     * @return array<string, array<int|string, mixed>|bool|float|int|string|\Stringable|null>
     */
    public function normalize(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $out[$k] = $this->normalizeValue($v);
        }
        return $out;
    }

    /**
     * @return array<int|string, mixed>|bool|float|int|string|\Stringable|null
     */
    private function normalizeValue(mixed $value): array|bool|float|int|string|\Stringable|null
    {
        if (is_scalar($value) || $value === null || $value instanceof \Stringable) {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_debug_type($value);
        }

        if (is_resource($value)) {
            return sprintf('resource(%s)', get_resource_type($value));
        }

        return 'unknown';
    }
}
