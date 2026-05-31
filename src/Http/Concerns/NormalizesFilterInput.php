<?php

namespace Zasetsu\Lookout\Http\Concerns;

use Illuminate\Http\Request;

trait NormalizesFilterInput
{
    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>|false
     */
    protected function scalarFilters(Request $request, array $filters): array|false
    {
        $result = [];

        foreach ($filters as $filter => $parameter) {
            $value = $this->scalarFilterValue($request, $parameter);

            if ($value === false) {
                return false;
            }

            if ($value !== null && $value !== '') {
                $result[$filter] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $filters
     * @param  array<string, array<int, string>>  $allowedValues
     * @return array<string, mixed>|false
     */
    protected function allowedScalarFilters(Request $request, array $filters, array $allowedValues): array|false
    {
        $result = $this->scalarFilters($request, $filters);

        if ($result === false) {
            return false;
        }

        foreach ($result as $filter => $value) {
            if (
                array_key_exists($filter, $allowedValues)
                && ! in_array((string) $value, $allowedValues[$filter], true)
            ) {
                return false;
            }
        }

        return $result;
    }

    protected function scalarFilterValue(Request $request, string $parameter): mixed
    {
        $value = $request->get($parameter);

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value)) {
            return false;
        }

        return $value;
    }

    protected function integerParameter(
        Request $request,
        string $parameter,
        int $default,
        int $min = 0,
        ?int $max = null,
        bool $clampMax = true,
    ): int|false {
        $value = $this->integerValue($this->scalarFilterValue($request, $parameter), $min, $max, $clampMax);

        return $value ?? $default;
    }

    protected function optionalIntegerFilter(
        Request $request,
        string $parameter,
        int $min = 0,
        ?int $max = null,
        bool $clampMax = true,
    ): int|false|null {
        return $this->integerValue($this->scalarFilterValue($request, $parameter), $min, $max, $clampMax);
    }

    protected function integerValue(mixed $value, int $min, ?int $max, bool $clampMax = true): int|false|null
    {
        if ($value === false) {
            return false;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $integer = (int) $value;
        } else {
            return false;
        }

        if ($integer < $min) {
            return false;
        }

        if ($max !== null && $integer > $max) {
            if (! $clampMax) {
                return false;
            }

            return $max;
        }

        return $integer;
    }

    protected function normalizeSinceFilter(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        $since = (string) $value;

        if (is_numeric($since)) {
            if ((float) $since <= 0.0) {
                return false;
            }

            return "-{$since} hours";
        }

        $since = strtolower(trim($since));

        if (preg_match('/^(\d+)\s*h$/', $since, $matches)) {
            return (int) $matches[1] > 0 ? "-{$matches[1]} hours" : false;
        }

        if (preg_match('/^(\d+)\s*d$/', $since, $matches)) {
            return (int) $matches[1] > 0 ? "-{$matches[1]} days" : false;
        }

        if (preg_match('/^(\d+)\s*m$/', $since, $matches)) {
            return (int) $matches[1] > 0 ? "-{$matches[1]} minutes" : false;
        }

        try {
            now()->parse($since);
        } catch (\Throwable) {
            return false;
        }

        return $since;
    }
}
