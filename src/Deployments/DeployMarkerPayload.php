<?php

namespace Zasetsu\Lookout\Deployments;

class DeployMarkerPayload
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function fromArray(array $attributes): array
    {
        $payload = [];

        foreach (['version', 'environment'] as $key) {
            $payload[$key] = trim((string) ($attributes[$key] ?? ''));
        }

        foreach (['commit', 'branch', 'author', 'source', 'compare_url', 'notes'] as $key) {
            $value = $attributes[$key] ?? null;
            $payload[$key] = is_scalar($value) ? trim((string) $value) : null;
            $payload[$key] = $payload[$key] !== '' ? $payload[$key] : null;
        }

        $payload['deployed_at'] = $attributes['deployed_at'] ?? now()->toDateTimeString();

        return $payload;
    }
}
