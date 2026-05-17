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
}
