<?php

namespace Zasetsu\Lookout\Storage;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseStorage implements StorageContract
{
    private string $connection;

    public function __construct()
    {
        $this->connection = config('lookout.storage.connection', 'lookout');
    }

    protected function storageConnection(): Connection
    {
        return DB::connection($this->connection);
    }

    protected function table(string $table): Builder
    {
        return $this->storageConnection()->table($table);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rowsToArray(Collection $rows): array
    {
        return $rows->map(fn ($row) => (array) $row)->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeThresholdRule(array $row): array
    {
        $channels = $row['channels'] ?? [];

        if (is_string($channels)) {
            $decoded = json_decode($channels, true);
            $channels = is_array($decoded) ? $decoded : [];
        }

        if (array_key_exists('id', $row)) {
            $row['id'] = (int) $row['id'];
        }

        if (array_key_exists('value', $row)) {
            $row['value'] = (float) $row['value'];
        }

        $row['channels'] = array_values(is_array($channels) ? $channels : []);
        $row['enabled'] = (bool) ($row['enabled'] ?? false);
        $row['window_minutes'] = (int) ($row['window_minutes'] ?? 0);
        $row['cooldown_minutes'] = (int) ($row['cooldown_minutes'] ?? 15);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function thresholdRulePayload(array $attributes): array
    {
        $payload = array_intersect_key($attributes, array_flip([
            'name',
            'metric',
            'condition',
            'value',
            'window_minutes',
            'cooldown_minutes',
            'channels',
            'enabled',
            'last_triggered_at',
        ]));

        if (array_key_exists('channels', $payload)) {
            $payload['channels'] = json_encode(array_values(is_array($payload['channels']) ? $payload['channels'] : []));
        }

        if (array_key_exists('enabled', $payload)) {
            $payload['enabled'] = (bool) $payload['enabled'];
        }

        if (array_key_exists('window_minutes', $payload)) {
            $payload['window_minutes'] = (int) $payload['window_minutes'];
        }

        if (array_key_exists('cooldown_minutes', $payload)) {
            $payload['cooldown_minutes'] = (int) $payload['cooldown_minutes'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeDeployMarker(array $row): array
    {
        unset($row['identity_hash']);

        if (array_key_exists('id', $row)) {
            $row['id'] = (int) $row['id'];
        }

        foreach (['version', 'environment', 'commit', 'branch', 'author', 'source', 'compare_url', 'notes', 'deployed_at', 'created_at', 'updated_at'] as $key) {
            $row[$key] = isset($row[$key]) && $row[$key] !== '' ? (string) $row[$key] : null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function deployMarkerPayload(array $attributes): array
    {
        $payload = [];

        foreach (['version', 'environment'] as $key) {
            $value = trim((string) ($attributes[$key] ?? ''));

            if ($value === '') {
                throw new \InvalidArgumentException("Deploy marker {$key} is required.");
            }

            $payload[$key] = $value;
        }

        foreach (['commit', 'branch', 'author', 'source', 'compare_url', 'notes'] as $key) {
            $value = $attributes[$key] ?? null;
            $payload[$key] = is_scalar($value) ? trim((string) $value) : null;
            $payload[$key] = $payload[$key] !== '' ? $payload[$key] : null;
        }

        $deployedAt = $attributes['deployed_at'] ?? now()->toDateTimeString();
        $payload['deployed_at'] = $deployedAt instanceof \DateTimeInterface
            ? $deployedAt->format('Y-m-d H:i:s')
            : now()->parse((string) $deployedAt)->toDateTimeString();
        $payload['identity_hash'] = $this->deployMarkerIdentityHash($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function deployMarkerIdentityHash(array $payload): string
    {
        return hash('sha256', implode("\0", [
            (string) $payload['environment'],
            (string) $payload['version'],
            (string) ($payload['commit'] ?? ''),
        ]));
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    protected function paginate(Builder $query, int $limit, int $offset): array
    {
        $total = (clone $query)->count();
        $items = $this->rowsToArray($query->offset($offset)->limit($limit)->get());

        return ['data' => $items, 'total' => $total];
    }

    protected function hourBucketExpression(string $column = 'timestamp'): string
    {
        return match ($this->storageConnection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00')",
            'pgsql' => "to_char({$column}::timestamp, 'YYYY-MM-DD HH24:00')",
            default => "strftime('%Y-%m-%d %H:00', {$column})",
        };
    }

    public function storeTrace(array $context): void
    {
        $this->table('lookout_traces')->insert($context);
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
            $this->table('lookout_events')->insert($chunk);
        }
    }

    public function storeTraceBatch(array $context, array $events): void
    {
        $this->storageConnection()->transaction(function () use ($context, $events) {
            $this->storeTrace($context);
            $this->storeEvents($context['trace_id'], $events);
        });
    }

    public function getTraces(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $query = $this->table('lookout_traces')->orderBy('timestamp', 'desc');

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

        return $this->paginate($query, $limit, $offset);
    }

    public function getTrace(string $traceId): ?array
    {
        $trace = $this->table('lookout_traces')
            ->where('trace_id', $traceId)
            ->first();

        if ($trace === null) {
            return null;
        }

        return (array) $trace;
    }

    public function getEvents(string $traceId): array
    {
        return $this->rowsToArray($this->table('lookout_events')
            ->where('trace_id', $traceId)
            ->orderBy('timestamp', 'asc')
            ->get());
    }

    public function getExceptionGroups(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $query = $this->table('lookout_exception_groups')
            ->orderBy('last_seen', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['class'])) {
            $query->where('exception_class', 'like', "%{$filters['class']}%");
        }

        return $this->paginate($query, $limit, $offset);
    }

    public function getExceptionGroupStatusCounts(): array
    {
        $rows = $this->table('lookout_exception_groups')
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
        $group = $this->table('lookout_exception_groups')
            ->where('id', $groupId)
            ->first();

        return $group ? (array) $group : null;
    }

    public function resolveExceptionGroup(int $groupId): bool
    {
        return $this->table('lookout_exception_groups')
            ->where('id', $groupId)
            ->update(['status' => 'resolved', 'resolved_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()]) > 0;
    }

    public function ignoreExceptionGroup(int $groupId): bool
    {
        return $this->table('lookout_exception_groups')
            ->where('id', $groupId)
            ->update(['status' => 'ignored', 'updated_at' => now()->toDateTimeString()]) > 0;
    }

    public function getSlowQueries(int $threshold = 500, int $limit = 25): array
    {
        return $this->rowsToArray($this->table('lookout_events')
            ->where('event_type', 'query')
            ->where('duration', '>=', $threshold)
            ->orderBy('duration', 'desc')
            ->limit($limit)
            ->get());
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

    public function getAuditLog(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = $this->table('lookout_audit_log')->orderBy('created_at', 'desc');

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['since'])) {
            $query->where('created_at', '>=', $filters['since']);
        }

        return $this->paginate($query, $limit, $offset);
    }

    public function getHealth(): array
    {
        $traceCount = DB::connection($this->connection)->table('lookout_traces')->count();
        $eventCount = DB::connection($this->connection)->table('lookout_events')->count();

        $recentRequests = DB::connection($this->connection)
            ->table('lookout_traces')
            ->where('type', 'request')
            ->where('timestamp', '>=', now()->subMinutes(5)->toDateTimeString())
            ->count();

        $lastPrune = $this->table('lookout_audit_log')
            ->where('action', 'prune_run')
            ->orderBy('created_at', 'desc')
            ->first();

        $lastPruneDetails = $lastPrune ? json_decode((string) $lastPrune->details, true) : [];

        return [
            'status' => 'ok',
            'storage_driver' => config('lookout.storage.driver', 'sqlite'),
            'storage_connection' => $this->connection,
            'storage_size_bytes' => null,
            'storage_size_mb' => null,
            'trace_count' => $traceCount,
            'event_count' => $eventCount,
            'recent_requests_5m' => $recentRequests,
            'retention_days' => (int) config('lookout.retention.days', 14),
            'prune_chance' => (int) config('lookout.retention.prune_chance', 1000),
            'last_prune_at' => $lastPrune->created_at ?? null,
            'last_prune_deleted_traces' => is_array($lastPruneDetails) ? ($lastPruneDetails['deleted_traces'] ?? null) : null,
            'payload_budget' => $this->getPayloadBudgetStats(),
        ];
    }

    public function getPayloadBudgetStats(): array
    {
        $totalRequestBodies = $this->table('lookout_traces')
            ->where('type', 'request')
            ->whereNotNull('request_body')
            ->count();

        $truncatedBodies = $this->table('lookout_traces')
            ->where('type', 'request')
            ->where('request_body', 'like', '%"_lookout_truncated":true%')
            ->count();

        $largestOriginalSize = $this->table('lookout_traces')
            ->where('type', 'request')
            ->where('request_body', 'like', '%"_lookout_truncated":true%')
            ->orderBy('timestamp', 'desc')
            ->limit(1000)
            ->pluck('request_body')
            ->map(function ($body): int {
                $decoded = json_decode((string) $body, true);

                if (! is_array($decoded)) {
                    return 0;
                }

                $size = $decoded['_lookout_original_size'] ?? 0;

                return is_numeric($size) ? (int) $size : 0;
            })
            ->max() ?? 0;

        return [
            'max_request_body_bytes' => (int) config('lookout.ingestion.max_request_body_bytes', 16384),
            'request_bodies' => $totalRequestBodies,
            'truncated_request_bodies' => $truncatedBodies,
            'largest_original_request_body_bytes' => $largestOriginalSize,
        ];
    }

    public function upsertDeployMarker(array $attributes): array
    {
        return $this->storageConnection()->transaction(function () use ($attributes): array {
            $payload = $this->deployMarkerPayload($attributes);
            $now = now()->toDateTimeString();
            $identityHash = $payload['identity_hash'];
            $created = $this->table('lookout_deploy_markers')->insertOrIgnore(array_merge($payload, [
                'created_at' => $now,
                'updated_at' => $now,
            ])) > 0;

            if (! $created) {
                $this->table('lookout_deploy_markers')
                    ->where('identity_hash', $identityHash)
                    ->update(array_merge($payload, [
                        'updated_at' => $now,
                    ]));
            }

            $marker = $this->table('lookout_deploy_markers')
                ->where('identity_hash', $identityHash)
                ->first();

            return [
                'marker' => $marker ? $this->normalizeDeployMarker((array) $marker) : [],
                'created' => $created,
            ];
        });
    }

    public function getDeployMarkers(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = $this->table('lookout_deploy_markers')->orderBy('deployed_at', 'desc');

        if (isset($filters['environment'])) {
            $query->where('environment', $filters['environment']);
        }

        if (isset($filters['version'])) {
            $query->where('version', $filters['version']);
        }

        if (isset($filters['since'])) {
            $query->where('deployed_at', '>=', $filters['since']);
        }

        $result = $this->paginate($query, $limit, $offset);
        $result['data'] = array_map(fn (array $row): array => $this->normalizeDeployMarker($row), $result['data']);

        return $result;
    }

    public function getLatestDeployMarker(?string $environment = null): ?array
    {
        $query = $this->table('lookout_deploy_markers')
            ->orderBy('deployed_at', 'desc')
            ->orderBy('id', 'desc');

        if ($environment !== null && $environment !== '') {
            $query->where('environment', $environment);
        }

        $marker = $query->first();

        return $marker ? $this->normalizeDeployMarker((array) $marker) : null;
    }

    public function getDeployMarkersBetween(string $from, string $to, ?string $environment = null): array
    {
        $query = $this->table('lookout_deploy_markers')
            ->where('deployed_at', '>=', $from)
            ->where('deployed_at', '<=', $to)
            ->orderBy('deployed_at')
            ->orderBy('id');

        if ($environment !== null && $environment !== '') {
            $query->where('environment', $environment);
        }

        return array_map(
            fn (array $row): array => $this->normalizeDeployMarker($row),
            $this->rowsToArray($query->get())
        );
    }

    protected function getDeployMarkerById(int $id): ?array
    {
        $marker = $this->table('lookout_deploy_markers')
            ->where('id', $id)
            ->first();

        return $marker ? $this->normalizeDeployMarker((array) $marker) : null;
    }

    public function getEnabledThresholds(): array
    {
        return array_map(fn (array $row): array => $this->normalizeThresholdRule($row), $this->rowsToArray($this->table('lookout_thresholds')
            ->where('enabled', true)
            ->get()));
    }

    public function getThresholdRules(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = $this->table('lookout_thresholds')->orderBy('id', 'desc');

        if (isset($filters['metric'])) {
            $query->where('metric', $filters['metric']);
        }

        if (isset($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }

        if (array_key_exists('enabled', $filters)) {
            $query->where('enabled', (bool) $filters['enabled']);
        }

        if (isset($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        $result = $this->paginate($query, $limit, $offset);
        $result['data'] = array_map(fn (array $row): array => $this->normalizeThresholdRule($row), $result['data']);

        return $result;
    }

    public function getThresholdRule(int $ruleId): ?array
    {
        $rule = $this->table('lookout_thresholds')
            ->where('id', $ruleId)
            ->first();

        return $rule ? $this->normalizeThresholdRule((array) $rule) : null;
    }

    public function createThresholdRule(array $attributes): array
    {
        $now = now()->toDateTimeString();
        $payload = array_merge([
            'cooldown_minutes' => 15,
            'channels' => json_encode([]),
            'enabled' => true,
            'last_triggered_at' => null,
        ], $this->thresholdRulePayload($attributes), [
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) $this->table('lookout_thresholds')->insertGetId($payload);

        return $this->getThresholdRule($id) ?? [];
    }

    public function updateThresholdRule(int $ruleId, array $attributes): array
    {
        $payload = $this->thresholdRulePayload($attributes);
        $payload['updated_at'] = now()->toDateTimeString();

        $this->table('lookout_thresholds')
            ->where('id', $ruleId)
            ->update($payload);

        $rule = $this->getThresholdRule($ruleId);

        if ($rule === null) {
            throw new \InvalidArgumentException("Threshold rule [{$ruleId}] does not exist.");
        }

        return $rule;
    }

    public function setThresholdRuleEnabled(int $ruleId, bool $enabled): array
    {
        return $this->updateThresholdRule($ruleId, ['enabled' => $enabled]);
    }

    public function deleteThresholdRule(int $ruleId): bool
    {
        return $this->table('lookout_thresholds')
            ->where('id', $ruleId)
            ->delete() > 0;
    }

    public function getThresholdMetricValue(string $metric, int $windowMinutes): float
    {
        $since = now()->subMinutes($windowMinutes)->toDateTimeString();

        return match ($metric) {
            'request_duration' => (float) ($this->table('lookout_traces')
                ->where('type', 'request')
                ->where('timestamp', '>=', $since)
                ->avg('duration') ?? 0),
            'exception_count' => (float) $this->table('lookout_events')
                ->where('event_type', 'exception')
                ->where('timestamp', '>=', $since)
                ->count(),
            'slow_query_count' => (float) $this->table('lookout_events')
                ->where('event_type', 'query')
                ->where('duration', '>=', 500)
                ->where('timestamp', '>=', $since)
                ->count(),
            'failed_job_count' => (float) $this->table('lookout_events')
                ->where('event_type', 'job_failed')
                ->where('timestamp', '>=', $since)
                ->count(),
            'request_duration_p95' => $this->requestDurationPercentile($since, 95),
            'error_rate' => $this->requestErrorRate($since),
            'outgoing_http_failure_count' => $this->outgoingHttpFailureCount($since),
            default => 0.0,
        };
    }

    protected function requestDurationPercentile(string $since, int $percentile): float
    {
        $query = $this->table('lookout_traces')
            ->where('type', 'request')
            ->whereNotNull('duration')
            ->where('timestamp', '>=', $since);

        $total = $query->count();

        if ($total === 0) {
            return 0.0;
        }

        $rank = (int) ceil(($percentile / 100) * $total);
        $offset = max(0, min($rank - 1, $total - 1));

        return (float) ($query->orderBy('duration')->offset($offset)->limit(1)->value('duration') ?? 0);
    }

    protected function requestErrorRate(string $since): float
    {
        $total = $this->table('lookout_traces')
            ->where('type', 'request')
            ->where('timestamp', '>=', $since)
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $failures = $this->table('lookout_traces')
            ->where('type', 'request')
            ->where('response_status', '>=', 400)
            ->where('timestamp', '>=', $since)
            ->count();

        return round(($failures / $total) * 100, 2);
    }

    protected function outgoingHttpFailureCount(string $since): float
    {
        $query = $this->table('lookout_events')
            ->where('event_type', 'outgoing_http')
            ->where('timestamp', '>=', $since);

        $driver = $this->storageConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return (float) $query
                ->where(function (Builder $query) {
                    $query->whereRaw("(payload::jsonb ->> 'failed') = 'true'")
                        ->orWhereRaw("((payload::jsonb ->> 'response_status') ~ '^[0-9]+$' AND (payload::jsonb ->> 'response_status')::integer >= 400)");
                })
                ->count();
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return (float) $query
                ->where(function (Builder $query) {
                    $query->whereRaw("CASE WHEN JSON_VALID(payload) THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.failed')) ELSE NULL END = 'true'")
                        ->orWhereRaw("CAST(CASE WHEN JSON_VALID(payload) THEN JSON_UNQUOTE(JSON_EXTRACT(payload, '$.response_status')) ELSE '0' END AS UNSIGNED) >= 400");
                })
                ->count();
        }

        return (float) $query
            ->whereRaw('json_valid(payload) = 1')
            ->where(function (Builder $query) {
                $query->whereRaw("json_extract(payload, '$.failed') = 1")
                    ->orWhereRaw("CAST(json_extract(payload, '$.response_status') AS INTEGER) >= 400");
            })
            ->count();
    }

    public function claimThresholdDispatchSlot(int $thresholdId, int $cooldownMinutes): bool
    {
        $cooldown = now()->subMinutes(max($cooldownMinutes, 1))->toDateTimeString();
        $claimedAt = now()->toDateTimeString();

        return $this->table('lookout_thresholds')
            ->where('id', $thresholdId)
            ->where(function ($query) use ($cooldown) {
                $query->whereNull('last_triggered_at')
                    ->orWhere('last_triggered_at', '<', $cooldown);
            })
            ->update([
                'last_triggered_at' => $claimedAt,
                'updated_at' => $claimedAt,
            ]) > 0;
    }

    public function releaseThresholdDispatchSlot(int $thresholdId, ?string $previousLastTriggeredAt, ?string $expectedLastTriggeredAt = null): void
    {
        $query = $this->table('lookout_thresholds')
            ->where('id', $thresholdId);

        if ($expectedLastTriggeredAt !== null) {
            $query->where('last_triggered_at', $expectedLastTriggeredAt);
        }

        $query->update([
            'last_triggered_at' => $previousLastTriggeredAt,
            'updated_at' => now()->toDateTimeString(),
        ]);
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
        $hourExpression = $this->hourBucketExpression();

        $rows = DB::connection($this->connection)
            ->table('lookout_traces')
            ->selectRaw($hourExpression.' as hour, COUNT(*) as count, ROUND(AVG(duration)) as avg_duration')
            ->where('type', 'request')
            ->where('timestamp', '>=', $sinceDate)
            ->groupByRaw($hourExpression)
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
        $hourExpression = $this->hourBucketExpression();

        $rows = DB::connection($this->connection)
            ->table('lookout_events')
            ->selectRaw($hourExpression.' as hour, COUNT(*) as count')
            ->where('event_type', $eventType)
            ->where('timestamp', '>=', $sinceDate)
            ->groupByRaw($hourExpression)
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
