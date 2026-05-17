<?php

namespace Zasetsu\Lookout\Http\Support;

class Payload
{
    /**
     * @return array<mixed>
     */
    public static function decode(mixed $payload): array
    {
        if (! is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<mixed>  $payload
     */
    public static function string(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<int, string>
     */
    public static function stringList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        if (is_string($value) || is_int($value) || is_float($value)) {
            return [(string) $value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn (string|int|float $item): string => (string) $item,
            array_filter($value, fn (mixed $item): bool => is_string($item) || is_int($item) || is_float($item))
        ));
    }

    /**
     * @param  array<mixed>  $payload
     */
    public static function bool(array $payload, string $key, bool $default = false): bool
    {
        $value = $payload[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param  array<mixed>  $payload
     */
    public static function number(array $payload, string $key, float $default = 0.0): float
    {
        $value = $payload[$key] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }
}
