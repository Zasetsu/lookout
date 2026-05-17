<?php

namespace Zasetsu\Lookout\Storage;

use Illuminate\Support\Facades\DB;

class SqliteStorage implements StorageContract
{
    private string $connection;

    public function __construct()
    {
        $this->connection = config('lookout.storage.connection', 'lookout');
        $this->configurePragmas();
    }

    protected function configurePragmas(): void
    {
        $path = config('lookout.storage.path', storage_path('lookout/lookout.sqlite'));

        if ($path !== ':memory:' && ! file_exists($path)) {
            return;
        }

        $pragmas = config('lookout.storage.pragmas', []);

        foreach ($pragmas as $pragma => $value) {
            if (is_string($value)) {
                DB::connection($this->connection)->statement("PRAGMA {$pragma} = {$value}");
            } elseif (is_int($value)) {
                DB::connection($this->connection)->statement("PRAGMA {$pragma} = {$value}");
            }
        }
    }

    public function storeTrace(array $context): void
    {
        DB::connection($this->connection)->table('lookout_traces')->insert($context);
    }

    public function storeEvents(string $traceId, array $events): void
    {
        if (empty($events)) {
            return;
        }

        $events = array_map(function (array $event) use ($traceId) {
            $event['trace_id'] = $traceId;
            $event['created_at'] = now()->toDateTimeString();

            return $event;
        }, $events);

        $batchSize = (int) config('lookout.ingestion.batch_size', 100);

        foreach (array_chunk($events, $batchSize) as $chunk) {
            DB::connection($this->connection)->table('lookout_events')->insert($chunk);
        }
    }

    public function storeTraceBatch(array $context, array $events): void
    {
        DB::connection($this->connection)->transaction(function () use ($context, $events) {
            $this->storeTrace($context);
            $this->storeEvents($context['trace_id'], $events);
        });
    }

    public function getTraces(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $query = DB::connection($this->connection)->table('lookout_traces')->orderBy('timestamp', 'desc');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['method'])) {
            $query->where('method', $filters['method']);
        }
        if (isset($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }
        if (isset($filters['min_duration'])) {
            $query->where('duration', '>=', $filters['min_duration']);
        }
        if (isset($filters['response_status'])) {
            $query->where('response_status', $filters['response_status']);
        }
        if (isset($filters['since'])) {
            $query->where('timestamp', '>=', $filters['since']);
        }
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $total = $query->count();
        $items = $query->offset($offset)->limit($limit)->get()->map(fn ($row) => (array) $row)->all();

        return ['data' => $items, 'total' => $total];
    }

    public function getTrace(string $traceId): ?array
    {
        $trace = DB::connection($this->connection)
            ->table('lookout_traces')
            ->where('trace_id', $traceId)
            ->first();

        if ($trace === null) {
            return null;
        }

        return (array) $trace;
    }

    public function getEvents(string $traceId): array
    {
        return DB::connection($this->connection)
            ->table('lookout_events')
            ->where('trace_id', $traceId)
            ->orderBy('timestamp', 'asc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function getExceptionGroups(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $query = DB::connection($this->connection)
            ->table('lookout_exception_groups')
            ->orderBy('last_seen', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['class'])) {
            $query->where('exception_class', 'like', "%{$filters['class']}%");
        }

        $total = $query->count();
        $items = $query->offset($offset)->limit($limit)->get()->map(fn ($row) => (array) $row)->all();

        return ['data' => $items, 'total' => $total];
    }

    public function getExceptionGroupStatusCounts(): array
    {
        $rows = DB::connection($this->connection)
            ->table('lookout_exception_groups')
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        $counts = ['unresolved' => 0, 'resolved' => 0, 'ignored' => 0];
        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->count;
        }

        return $counts;
    }

    public function getExceptionGroup(int $groupId): ?array
    {
        $group = DB::connection($this->connection)
            ->table('lookout_exception_groups')
            ->where('id', $groupId)
            ->first();

        return $group ? (array) $group : null;
    }

    public function resolveExceptionGroup(int $groupId): bool
    {
        return DB::connection($this->connection)
            ->table('lookout_exception_groups')
            ->where('id', $groupId)
            ->update(['status' => 'resolved', 'resolved_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()]) > 0;
    }

    public function ignoreExceptionGroup(int $groupId): bool
    {
        return DB::connection($this->connection)
            ->table('lookout_exception_groups')
            ->where('id', $groupId)
            ->update(['status' => 'ignored', 'updated_at' => now()->toDateTimeString()]) > 0;
    }

    public function getSlowQueries(int $threshold = 500, int $limit = 25): array
    {
        return DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', 'query')
            ->where('duration', '>=', $threshold)
            ->orderBy('duration', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function getSummary(string $since = '-24 hours'): array
    {
        $sinceDate = now()->parse($since)->toDateTimeString();

        $totalRequests = DB::connection($this->connection)
            ->table('lookout_traces')
            ->where('type', 'request')
            ->where('timestamp', '>=', $sinceDate)
            ->count();

        $avgDuration = DB::connection($this->connection)
            ->table('lookout_traces')
            ->where('type', 'request')
            ->where('timestamp', '>=', $sinceDate)
            ->avg('duration');

        $totalExceptions = DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', 'exception')
            ->where('timestamp', '>=', $sinceDate)
            ->count();

        $unresolvedGroups = DB::connection($this->connection)
            ->table('lookout_exception_groups')
            ->where('status', 'unresolved')
            ->count();

        $slowQueries = DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', 'query')
            ->where('duration', '>=', 500)
            ->where('timestamp', '>=', $sinceDate)
            ->count();

        $failedJobs = DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', 'job_failed')
            ->where('timestamp', '>=', $sinceDate)
            ->count();

        $topSlowRoutes = DB::connection($this->connection)
            ->table('lookout_traces')
            ->selectRaw('name, AVG(duration) as avg_duration, COUNT(*) as count')
            ->where('type', 'request')
            ->where('timestamp', '>=', $sinceDate)
            ->groupBy('name')
            ->orderByDesc('avg_duration')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return [
            'total_requests' => $totalRequests,
            'avg_duration' => round($avgDuration ?? 0, 2),
            'total_exceptions' => $totalExceptions,
            'unresolved_groups' => $unresolvedGroups,
            'slow_queries' => $slowQueries,
            'failed_jobs' => $failedJobs,
            'top_slow_routes' => $topSlowRoutes,
            'since' => $sinceDate,
        ];
    }

    public function prune(int $olderThanDays = 14): int
    {
        if ($olderThanDays <= 0) {
            throw new \InvalidArgumentException('Lookout retention days must be greater than zero.');
        }

        $cutoff = now()->subDays($olderThanDays)->toDateTimeString();

        return DB::connection($this->connection)
            ->table('lookout_traces')
            ->where('timestamp', '<', $cutoff)
            ->delete();
    }

    public function upsertExceptionGroup(string $fingerprint, array $data): void
    {
        DB::connection($this->connection)->transaction(function () use ($fingerprint, $data): void {
            $updated = DB::connection($this->connection)
                ->table('lookout_exception_groups')
                ->where('fingerprint', $fingerprint)
                ->update([
                    'last_seen' => $data['last_seen'] ?? now()->toDateTimeString(),
                    'occurrence_count' => DB::raw('occurrence_count + 1'),
                    'message' => $data['message'] ?? DB::raw('message'),
                    'status' => DB::raw("CASE WHEN status = 'ignored' THEN status ELSE 'unresolved' END"),
                    'resolved_at' => DB::raw("CASE WHEN status = 'ignored' THEN resolved_at ELSE NULL END"),
                    'updated_at' => now()->toDateTimeString(),
                ]);

            if ($updated === 0) {
                DB::connection($this->connection)
                    ->table('lookout_exception_groups')
                    ->insert(array_merge($data, [
                        'fingerprint' => $fingerprint,
                        'occurrence_count' => 1,
                        'status' => 'unresolved',
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ]));
            }
        });
    }

    public function logAudit(string $action, ?string $userId = null, ?string $ip = null, ?array $details = null): void
    {
        DB::connection($this->connection)->table('lookout_audit_log')->insert([
            'action' => $action,
            'user_id' => $userId,
            'ip' => $ip,
            'details' => $details ? json_encode($details) : null,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function getHealth(): array
    {
        $path = config('lookout.storage.path');
        $fileSize = file_exists($path) ? filesize($path) : 0;

        $traceCount = DB::connection($this->connection)->table('lookout_traces')->count();
        $eventCount = DB::connection($this->connection)->table('lookout_events')->count();

        $recentRequests = DB::connection($this->connection)
            ->table('lookout_traces')
            ->where('type', 'request')
            ->where('timestamp', '>=', now()->subMinutes(5)->toDateTimeString())
            ->count();

        return [
            'status' => 'ok',
            'storage_size_bytes' => $fileSize,
            'storage_size_mb' => round($fileSize / 1048576, 2),
            'trace_count' => $traceCount,
            'event_count' => $eventCount,
            'recent_requests_5m' => $recentRequests,
        ];
    }

    public function getEventsByType(string $eventType, array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $query = DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', $eventType)
            ->orderBy('timestamp', 'desc');

        if (isset($filters['since'])) {
            $query->where('timestamp', '>=', $filters['since']);
        }

        $total = $query->count();
        $items = $query->offset($offset)->limit($limit)->get()->map(fn ($row) => (array) $row)->all();

        return ['data' => $items, 'total' => $total];
    }

    public function getCacheStats(string $since = '-24 hours'): array
    {
        $sinceDate = now()->parse($since)->toDateTimeString();

        $hits = DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', 'cache')
            ->where('payload', 'like', '%"operation":"cache_hit"%')
            ->where('timestamp', '>=', $sinceDate)
            ->count();

        $misses = DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', 'cache')
            ->where('payload', 'like', '%"operation":"cache_miss"%')
            ->where('timestamp', '>=', $sinceDate)
            ->count();

        $writes = DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', 'cache')
            ->where('payload', 'like', '%"operation":"cache_write"%')
            ->where('timestamp', '>=', $sinceDate)
            ->count();

        $total = $hits + $misses;
        $hitRate = $total > 0 ? round(($hits / $total) * 100, 1) : 0;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'writes' => $writes,
            'hit_rate' => $hitRate,
            'total' => $total,
        ];
    }

    public function getRequestVolumeByHour(string $since = '-24 hours'): array
    {
        $sinceDate = now()->parse($since)->toDateTimeString();

        $rows = DB::connection($this->connection)
            ->table('lookout_traces')
            ->selectRaw("strftime('%Y-%m-%d %H:00', timestamp) as hour, COUNT(*) as count, ROUND(AVG(duration)) as avg_duration")
            ->where('type', 'request')
            ->where('timestamp', '>=', $sinceDate)
            ->groupBy('hour')
            ->orderBy('hour', 'asc')
            ->get();

        $hours = [];
        $current = now()->parse($since);

        while ($current <= now()) {
            $key = $current->format('Y-m-d H:00');
            $hours[$key] = ['hour' => $key, 'count' => 0, 'avg_duration' => 0];
            $current->addHour();
        }

        foreach ($rows as $row) {
            $row = (array) $row;
            if (isset($hours[$row['hour']])) {
                $hours[$row['hour']] = $row;
            }
        }

        return array_values($hours);
    }

    public function getStatusDistribution(string $since = '-24 hours'): array
    {
        $sinceDate = now()->parse($since)->toDateTimeString();

        $rows = DB::connection($this->connection)
            ->table('lookout_traces')
            ->selectRaw("CASE WHEN response_status >= 200 AND response_status < 300 THEN '2xx' WHEN response_status >= 300 AND response_status < 400 THEN '3xx' WHEN response_status >= 400 AND response_status < 500 THEN '4xx' WHEN response_status >= 500 THEN '5xx' ELSE 'unknown' END as range, COUNT(*) as count")
            ->where('type', 'request')
            ->where('timestamp', '>=', $sinceDate)
            ->groupBy('range')
            ->get();

        $dist = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];
        $total = 0;

        foreach ($rows as $row) {
            $r = (array) $row;
            $dist[$r['range']] = (int) $r['count'];
            $total += (int) $r['count'];
        }

        return ['distribution' => $dist, 'total' => $total];
    }

    public function getTopExceptions(int $limit = 5): array
    {
        return DB::connection($this->connection)
            ->table('lookout_exception_groups')
            ->where('status', 'unresolved')
            ->orderBy('occurrence_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function getEventsByHour(string $eventType, string $since = '-24 hours'): array
    {
        $sinceDate = now()->parse($since)->toDateTimeString();

        $rows = DB::connection($this->connection)
            ->table('lookout_events')
            ->selectRaw("strftime('%Y-%m-%d %H:00', timestamp) as hour, COUNT(*) as count")
            ->where('event_type', $eventType)
            ->where('timestamp', '>=', $sinceDate)
            ->groupBy('hour')
            ->orderBy('hour', 'asc')
            ->get();

        $hours = [];
        $current = now()->parse($since);

        while ($current <= now()) {
            $key = $current->format('Y-m-d H:00');
            $hours[$key] = ['hour' => $key, 'count' => 0];
            $current->addHour();
        }

        foreach ($rows as $row) {
            $row = (array) $row;
            if (isset($hours[$row['hour']])) {
                $hours[$row['hour']] = $row;
            }
        }

        return array_values($hours);
    }

    public function getQueryDurationBuckets(int $limit = 50): array
    {
        $rows = DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', 'query')
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();

        $buckets = ['0-10ms' => 0, '10-50ms' => 0, '50-100ms' => 0, '100-500ms' => 0, '500-1000ms' => 0, '1s+' => 0];

        foreach ($rows as $row) {
            $row = (array) $row;
            $d = (int) ($row['duration'] ?? 0);

            match (true) {
                $d < 10 => $buckets['0-10ms']++,
                $d < 50 => $buckets['10-50ms']++,
                $d < 100 => $buckets['50-100ms']++,
                $d < 500 => $buckets['100-500ms']++,
                $d < 1000 => $buckets['500-1000ms']++,
                default => $buckets['1s+']++,
            };
        }

        $max = max($buckets) ?: 1;

        return ['buckets' => $buckets, 'max' => $max, 'total' => count($rows)];
    }

    public function getTotalEventsCount(string $eventType): int
    {
        return DB::connection($this->connection)
            ->table('lookout_events')
            ->where('event_type', $eventType)
            ->count();
    }
}
