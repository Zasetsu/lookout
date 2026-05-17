<?php

namespace Zasetsu\Lookout\Pipeline;

class RedactionPolicy
{
    /**
     * @param  array<int, string>  $patterns
     */
    public function __construct(
        private array $patterns,
    ) {}

    public static function fromConfig(): self
    {
        return new self(array_merge(
            config('lookout.redaction.patterns', []),
            config('lookout.redaction.custom', [])
        ));
    }

    /**
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return $this->patterns;
    }

    public function isSensitiveKey(string $key): bool
    {
        $normalizedKey = $this->normalizeForComparison($key);

        foreach ($this->patterns as $pattern) {
            if (str_contains($normalizedKey, $this->normalizeForComparison($pattern))) {
                return true;
            }
        }

        return false;
    }

    public function containsSensitiveContent(string $text): bool
    {
        $normalized = $this->normalizeForComparison($text);

        foreach ($this->patterns as $pattern) {
            if (str_contains($normalized, $this->normalizeForComparison($pattern))) {
                return true;
            }
        }

        return false;
    }

    public function patternRegex(string $pattern): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $pattern, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($parts)) {
            return preg_quote($pattern, '/');
        }

        return implode('[\s_-]*', array_map(fn (string $part): string => preg_quote($part, '/'), $parts));
    }

    public function normalizeForComparison(string $value): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? $value);
    }
}
