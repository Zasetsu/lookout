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
}
