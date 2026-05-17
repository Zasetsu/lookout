<?php

namespace Zasetsu\Lookout\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Zasetsu\Lookout\Http\Concerns\NormalizesFilterInput;
use Zasetsu\Lookout\Storage\StorageContract;

class ApiController extends Controller
{
    use NormalizesFilterInput;

    public function __construct(
        private StorageContract $storage,
    ) {
        $this->middleware('throttle:120');

        $this->middleware(function ($request, $next) {
            if (! config('lookout.api.enabled', false)) {
                abort(404);
            }

            $token = config('lookout.api.token');

            if (empty($token)) {
                abort(404);
            }

            $provided = $request->bearerToken();

            if (! is_string($provided) || ! hash_equals($token, $provided)) {
                abort(401, 'Unauthorized');
            }

            return $next($request);
        });
    }

    public function health(): JsonResponse
    {
        return response()->json($this->storage->getHealth());
    }

    public function summary(Request $request): JsonResponse
    {
        $rawSince = $request->get('since', '-24 hours');

        if (! is_string($rawSince) && ! is_numeric($rawSince)) {
            return response()->json(['message' => 'Invalid since parameter.'], 422);
        }

        $since = $this->normalizeSince((string) $rawSince);

        if ($since === null) {
            return response()->json(['message' => 'Invalid since parameter.'], 422);
        }

        return response()->json($this->storage->getSummary($since));
    }

    public function exceptions(Request $request): JsonResponse
    {
        $filters = $this->scalarFilters($request, [
            'status' => 'status',
            'class' => 'class',
        ]);

        if ($filters === false) {
            return response()->json(['message' => 'Invalid filter parameter.'], 422);
        }

        $limit = $this->integerParameter($request, 'limit', 50, 1, 200);
        $offset = $this->integerParameter($request, 'offset', 0, 0);

        if ($limit === false || $offset === false) {
            return response()->json(['message' => 'Invalid pagination parameter.'], 422);
        }

        $result = $this->storage->getExceptionGroups($filters, $limit, $offset);

        return response()->json([
            'data' => $result['data'],
            'meta' => ['total' => $result['total'], 'limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function requests(Request $request): JsonResponse
    {
        $slowerThan = $this->scalarFilterValue($request, 'slower_than');
        if ($slowerThan === false) {
            return response()->json(['message' => 'Invalid slower_than parameter.'], 422);
        }

        $minDuration = $this->normalizeDurationFilter($slowerThan);
        if ($minDuration === false) {
            return response()->json(['message' => 'Invalid slower_than parameter.'], 422);
        }

        $filters = $this->scalarFilters($request, [
            'status' => 'status',
            'name' => 'route',
        ]);

        if ($filters === false) {
            return response()->json(['message' => 'Invalid filter parameter.'], 422);
        }

        $responseStatus = $this->optionalIntegerFilter($request, 'response_status', 100, 599, false);
        if ($responseStatus === false) {
            return response()->json(['message' => 'Invalid response_status parameter.'], 422);
        }

        if ($responseStatus !== null) {
            $filters['response_status'] = $responseStatus;
        }

        $filters = array_filter(array_merge([
            'type' => 'request',
            'min_duration' => $minDuration,
        ], $filters));

        $limit = $this->integerParameter($request, 'limit', 100, 1, 500);
        $offset = $this->integerParameter($request, 'offset', 0, 0);

        if ($limit === false || $offset === false) {
            return response()->json(['message' => 'Invalid pagination parameter.'], 422);
        }

        $result = $this->storage->getTraces($filters, $limit, $offset);

        return response()->json([
            'data' => $result['data'],
            'meta' => ['total' => $result['total'], 'limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function trace(string $traceId): JsonResponse
    {
        $trace = $this->storage->getTrace($traceId);

        if ($trace === null) {
            abort(404, 'Trace not found');
        }

        $events = $this->storage->getEvents($traceId);

        return response()->json([
            'data' => $trace,
            'events' => $events,
        ]);
    }

    protected function normalizeSince(string $since): ?string
    {
        if (is_numeric($since)) {
            if ((float) $since <= 0.0) {
                return null;
            }

            return "-{$since} hours";
        }

        $since = strtolower(trim($since));

        if (preg_match('/^(\d+)\s*h$/', $since, $m)) {
            if ((int) $m[1] <= 0) {
                return null;
            }

            return "-{$m[1]} hours";
        }

        if (preg_match('/^(\d+)\s*d$/', $since, $m)) {
            if ((int) $m[1] <= 0) {
                return null;
            }

            return "-{$m[1]} days";
        }

        if (preg_match('/^(\d+)\s*m$/', $since, $m)) {
            if ((int) $m[1] <= 0) {
                return null;
            }

            return "-{$m[1]} minutes";
        }

        try {
            now()->parse($since);
        } catch (\Throwable) {
            return null;
        }

        return $since;
    }

    protected function normalizeDurationFilter(mixed $value): int|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        return $this->parseDuration((string) $value);
    }

    protected function parseDuration(string $value): int|false
    {
        $value = strtolower(trim($value));

        if (preg_match('/^(\d+(?:\.\d+)?)\s*s$/', $value, $m)) {
            return $this->positiveMilliseconds((float) $m[1], 1000);
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*m$/', $value, $m)) {
            return $this->positiveMilliseconds((float) $m[1], 60000);
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*h$/', $value, $m)) {
            return $this->positiveMilliseconds((float) $m[1], 3600000);
        }

        if (preg_match('/^\d+$/', $value) === 1 && (int) $value > 0) {
            return (int) $value;
        }

        return false;
    }

    protected function positiveMilliseconds(float $value, int $multiplier): int|false
    {
        if ($value <= 0.0) {
            return false;
        }

        return (int) ($value * $multiplier);
    }
}
