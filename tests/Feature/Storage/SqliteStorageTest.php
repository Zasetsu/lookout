<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Zasetsu\Lookout\Storage\SqliteStorage;
use Zasetsu\Lookout\Storage\StorageContract;

function makeTraceContext(string $traceId, array $overrides = []): array
{
    return array_merge([
        'trace_id' => $traceId,
        'type' => 'request',
        'name' => '/api/test',
        'status' => 'success',
        'timestamp' => now()->toDateTimeString(),
        'duration' => 100,
        'memory_peak' => null,
        'user_id' => null,
        'ip' => null,
        'method' => 'GET',
        'url' => null,
        'request_headers' => null,
        'request_body' => null,
        'response_status' => 200,
        'response_headers' => null,
        'tags' => null,
        'environment' => 'testing',
    ], $overrides);
}

describe('SqliteStorage', function () {
    beforeEach(function () {
        $this->storage = new SqliteStorage;
    });

    it('implements StorageContract', function () {
        expect($this->storage)->toBeInstanceOf(StorageContract::class);
    });

    it('keeps read query plumbing behind focused helper methods', function () {
        $reflection = new ReflectionClass(SqliteStorage::class);

        expect($reflection->hasMethod('storageConnection'))->toBeTrue()
            ->and($reflection->hasMethod('table'))->toBeTrue()
            ->and($reflection->hasMethod('rowsToArray'))->toBeTrue()
            ->and($reflection->hasMethod('paginate'))->toBeTrue();
    });

    it('stores and retrieves a trace', function () {
        $context = [
            'trace_id' => Str::uuid()->toString(),
            'type' => 'request',
            'name' => '/api/test',
            'status' => 'success',
            'timestamp' => now()->toDateTimeString(),
            'duration' => 150,
            'memory_peak' => 2048000,
            'user_id' => null,
            'ip' => '127.0.0.1',
            'method' => 'GET',
            'url' => 'http://localhost/api/test',
            'request_headers' => null,
            'request_body' => null,
            'response_status' => 200,
            'response_headers' => null,
            'tags' => null,
            'environment' => 'testing',
        ];

        $this->storage->storeTrace($context);

        $trace = $this->storage->getTrace($context['trace_id']);
        expect($trace)->not->toBeNull();
        expect($trace['trace_id'])->toBe($context['trace_id']);
        expect($trace['type'])->toBe('request');
        expect($trace['name'])->toBe('/api/test');
    });

    it('returns null for non-existent trace', function () {
        $trace = $this->storage->getTrace(Str::uuid()->toString());

        expect($trace)->toBeNull();
    });

    it('stores events for a trace individually', function () {
        $traceId = Str::uuid()->toString();
        $context = makeTraceContext($traceId);

        $this->storage->storeTrace($context);

        $events = [
            [
                'event_type' => 'query',
                'timestamp' => now()->toDateTimeString(),
                'duration' => 50,
                'labels' => 'SELECT * FROM users',
                'payload' => json_encode(['sql' => 'SELECT * FROM users']),
                'tags' => null,
            ],
        ];

        $this->storage->storeEvents($traceId, $events);

        $storedEvents = $this->storage->getEvents($traceId);
        expect($storedEvents)->toHaveCount(1);
        expect($storedEvents[0]['event_type'])->toBe('query');
        expect($storedEvents[0]['trace_id'])->toBe($traceId);
    });

    it('does not store empty events', function () {
        $traceId = Str::uuid()->toString();
        $context = makeTraceContext($traceId);

        $this->storage->storeTrace($context);
        $this->storage->storeEvents($traceId, []);

        $storedEvents = $this->storage->getEvents($traceId);
        expect($storedEvents)->toHaveCount(0);
    });

    it('stores trace batch with events', function () {
        $traceId = Str::uuid()->toString();
        $context = makeTraceContext($traceId);

        $events = [
            [
                'event_type' => 'query',
                'timestamp' => now()->toDateTimeString(),
                'duration' => 50,
                'labels' => 'SELECT * FROM users',
                'payload' => json_encode(['sql' => 'SELECT * FROM users']),
                'tags' => null,
            ],
            [
                'event_type' => 'cache',
                'timestamp' => now()->toDateTimeString(),
                'duration' => 5,
                'labels' => 'Cache hit: users',
                'payload' => json_encode(['key' => 'users']),
                'tags' => null,
            ],
        ];

        $this->storage->storeTraceBatch($context, $events);

        $trace = $this->storage->getTrace($traceId);
        expect($trace)->not->toBeNull();

        $storedEvents = $this->storage->getEvents($traceId);
        expect($storedEvents)->toHaveCount(2);
        expect($storedEvents[0]['event_type'])->toBe('query');
        expect($storedEvents[1]['event_type'])->toBe('cache');
    });

    it('gets traces with filters', function () {
        $context = [
            'trace_id' => Str::uuid()->toString(),
            'type' => 'request',
            'name' => '/api/users',
            'status' => 'success',
            'timestamp' => now()->toDateTimeString(),
            'duration' => 200,
            'memory_peak' => null,
            'user_id' => null,
            'ip' => null,
            'method' => 'GET',
            'url' => null,
            'request_headers' => null,
            'request_body' => null,
            'response_status' => 200,
            'response_headers' => null,
            'tags' => null,
            'environment' => 'testing',
        ];

        $this->storage->storeTrace($context);

        $result = $this->storage->getTraces(['type' => 'request'], 25, 0);
        expect($result['total'])->toBeGreaterThanOrEqual(1);
        expect($result['data'])->toHaveCount(1);

        $emptyResult = $this->storage->getTraces(['type' => 'command'], 25, 0);
        expect($emptyResult['data'])->toHaveCount(0);
    });

    it('filters traces by status', function () {
        $successContext = makeTraceContext(Str::uuid()->toString(), ['status' => 'success']);
        $failedContext = makeTraceContext(Str::uuid()->toString(), ['status' => 'failed']);

        $this->storage->storeTrace($successContext);
        $this->storage->storeTrace($failedContext);

        $result = $this->storage->getTraces(['status' => 'success'], 25, 0);
        expect($result['total'])->toBe(1);
        expect($result['data'][0]['status'])->toBe('success');
    });

    it('filters traces by method', function () {
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['method' => 'GET']));
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['method' => 'POST']));

        $result = $this->storage->getTraces(['method' => 'GET'], 25, 0);
        expect($result['total'])->toBe(1);
    });

    it('filters traces by name with like', function () {
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['name' => '/api/users']));
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['name' => '/api/posts']));

        $result = $this->storage->getTraces(['name' => 'users'], 25, 0);
        expect($result['total'])->toBe(1);
        expect($result['data'][0]['name'])->toBe('/api/users');
    });

    it('filters traces by min_duration', function () {
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['duration' => 50]));
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['duration' => 500]));

        $result = $this->storage->getTraces(['min_duration' => 100], 25, 0);
        expect($result['total'])->toBe(1);
        expect($result['data'][0]['duration'])->toBe(500);
    });

    it('filters traces by response_status', function () {
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['response_status' => 200]));
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['response_status' => 500]));

        $result = $this->storage->getTraces(['response_status' => 500], 25, 0);
        expect($result['total'])->toBe(1);
    });

    it('filters traces by user_id', function () {
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['user_id' => 'user-1']));
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString(), ['user_id' => 'user-2']));

        $result = $this->storage->getTraces(['user_id' => 'user-1'], 25, 0);
        expect($result['total'])->toBe(1);
    });

    it('paginates traces', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString()));
        }

        $page1 = $this->storage->getTraces([], 2, 0);
        $page2 = $this->storage->getTraces([], 2, 2);

        expect($page1['total'])->toBe(5);
        expect($page1['data'])->toHaveCount(2);
        expect($page2['data'])->toHaveCount(2);
    });

    it('upserts exception groups', function () {
        $fingerprint = hash('sha256', 'RuntimeException|file.php|42');

        $this->storage->upsertExceptionGroup($fingerprint, [
            'exception_class' => 'RuntimeException',
            'file' => 'file.php',
            'line' => 42,
            'message' => 'Test error',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $groups = $this->storage->getExceptionGroups(['status' => 'unresolved'], 25, 0);
        expect($groups['total'])->toBe(1);
        expect($groups['data'][0]['exception_class'])->toBe('RuntimeException');

        $this->storage->upsertExceptionGroup($fingerprint, [
            'exception_class' => 'RuntimeException',
            'file' => 'file.php',
            'line' => 42,
            'message' => 'Updated error',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $groups = $this->storage->getExceptionGroups(['status' => 'unresolved'], 25, 0);
        expect($groups['data'][0]['occurrence_count'])->toBe(2);
        expect($groups['data'][0]['message'])->toBe('Updated error');
    });

    it('filters exception groups by status', function () {
        $fp1 = hash('sha256', 'Exception|a.php|1');
        $fp2 = hash('sha256', 'Exception|b.php|2');

        $this->storage->upsertExceptionGroup($fp1, [
            'exception_class' => 'RuntimeException',
            'file' => 'a.php',
            'line' => 1,
            'message' => 'A',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $this->storage->upsertExceptionGroup($fp2, [
            'exception_class' => 'LogicException',
            'file' => 'b.php',
            'line' => 2,
            'message' => 'B',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $result = $this->storage->getExceptionGroups(['status' => 'unresolved'], 25, 0);
        expect($result['total'])->toBe(2);
    });

    it('filters exception groups by class', function () {
        $fp1 = hash('sha256', 'Ex|c.php|1');
        $fp2 = hash('sha256', 'Ex|d.php|2');

        $this->storage->upsertExceptionGroup($fp1, [
            'exception_class' => 'RuntimeException',
            'file' => 'c.php',
            'line' => 1,
            'message' => 'C',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $this->storage->upsertExceptionGroup($fp2, [
            'exception_class' => 'LogicException',
            'file' => 'd.php',
            'line' => 2,
            'message' => 'D',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $result = $this->storage->getExceptionGroups(['class' => 'Runtime'], 25, 0);
        expect($result['total'])->toBe(1);
        expect($result['data'][0]['exception_class'])->toBe('RuntimeException');
    });

    it('resolves exception groups', function () {
        $fingerprint = hash('sha256', 'Exception|resolve.php|10');

        $this->storage->upsertExceptionGroup($fingerprint, [
            'exception_class' => 'Exception',
            'file' => 'resolve.php',
            'line' => 10,
            'message' => 'Test',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $groups = $this->storage->getExceptionGroups([], 25, 0);
        $groupId = $groups['data'][0]['id'];

        $result = $this->storage->resolveExceptionGroup($groupId);
        expect($result)->toBeTrue();

        $group = $this->storage->getExceptionGroup($groupId);
        expect($group['status'])->toBe('resolved');
        expect($group['resolved_at'])->not->toBeNull();
    });

    it('reopens resolved exception groups when they recur', function () {
        $fingerprint = hash('sha256', 'Exception|recurs.php|10');

        $this->storage->upsertExceptionGroup($fingerprint, [
            'exception_class' => 'Exception',
            'file' => 'recurs.php',
            'line' => 10,
            'message' => 'First failure',
            'first_seen' => now()->subMinute()->toDateTimeString(),
            'last_seen' => now()->subMinute()->toDateTimeString(),
        ]);

        $groupId = $this->storage->getExceptionGroups([], 25, 0)['data'][0]['id'];
        $this->storage->resolveExceptionGroup($groupId);

        $this->storage->upsertExceptionGroup($fingerprint, [
            'exception_class' => 'Exception',
            'file' => 'recurs.php',
            'line' => 10,
            'message' => 'Second failure',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $group = $this->storage->getExceptionGroup($groupId);

        expect($group['status'])->toBe('unresolved');
        expect($group['resolved_at'])->toBeNull();
        expect($group['occurrence_count'])->toBe(2);
        expect($group['message'])->toBe('Second failure');
    });

    it('ignores exception groups', function () {
        $fingerprint = hash('sha256', 'Exception|ignore.php|20');

        $this->storage->upsertExceptionGroup($fingerprint, [
            'exception_class' => 'Exception',
            'file' => 'ignore.php',
            'line' => 20,
            'message' => 'Ignored',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $groups = $this->storage->getExceptionGroups([], 25, 0);
        $groupId = $groups['data'][0]['id'];

        $result = $this->storage->ignoreExceptionGroup($groupId);
        expect($result)->toBeTrue();

        $group = $this->storage->getExceptionGroup($groupId);
        expect($group['status'])->toBe('ignored');
    });

    it('keeps ignored exception groups ignored when they recur', function () {
        $fingerprint = hash('sha256', 'Exception|ignored-recurs.php|20');

        $this->storage->upsertExceptionGroup($fingerprint, [
            'exception_class' => 'Exception',
            'file' => 'ignored-recurs.php',
            'line' => 20,
            'message' => 'Ignored once',
            'first_seen' => now()->subMinute()->toDateTimeString(),
            'last_seen' => now()->subMinute()->toDateTimeString(),
        ]);

        $groupId = $this->storage->getExceptionGroups([], 25, 0)['data'][0]['id'];
        $this->storage->ignoreExceptionGroup($groupId);

        $this->storage->upsertExceptionGroup($fingerprint, [
            'exception_class' => 'Exception',
            'file' => 'ignored-recurs.php',
            'line' => 20,
            'message' => 'Ignored again',
            'first_seen' => now()->toDateTimeString(),
            'last_seen' => now()->toDateTimeString(),
        ]);

        $group = $this->storage->getExceptionGroup($groupId);

        expect($group['status'])->toBe('ignored');
        expect($group['occurrence_count'])->toBe(2);
        expect($group['message'])->toBe('Ignored again');
    });

    it('returns null for non-existent exception group', function () {
        $group = $this->storage->getExceptionGroup(99999);
        expect($group)->toBeNull();
    });

    it('returns false when resolving non-existent group', function () {
        $result = $this->storage->resolveExceptionGroup(99999);
        expect($result)->toBeFalse();
    });

    it('returns false when ignoring non-existent group', function () {
        $result = $this->storage->ignoreExceptionGroup(99999);
        expect($result)->toBeFalse();
    });

    it('gets slow queries', function () {
        $traceId = Str::uuid()->toString();
        $this->storage->storeTrace(makeTraceContext($traceId));

        $this->storage->storeEvents($traceId, [
            [
                'event_type' => 'query',
                'timestamp' => now()->toDateTimeString(),
                'duration' => 600,
                'labels' => 'Slow query',
                'payload' => 'SELECT SLEEP(1)',
                'tags' => null,
            ],
            [
                'event_type' => 'query',
                'timestamp' => now()->toDateTimeString(),
                'duration' => 50,
                'labels' => 'Fast query',
                'payload' => 'SELECT 1',
                'tags' => null,
            ],
        ]);

        $slow = $this->storage->getSlowQueries(500, 25);
        expect($slow)->toHaveCount(1);
        expect($slow[0]['duration'])->toBe(600);
    });

    it('gets slow queries with custom threshold', function () {
        $traceId = Str::uuid()->toString();
        $this->storage->storeTrace(makeTraceContext($traceId));

        $this->storage->storeEvents($traceId, [
            [
                'event_type' => 'query',
                'timestamp' => now()->toDateTimeString(),
                'duration' => 150,
                'labels' => 'Medium query',
                'payload' => 'SELECT * FROM big_table',
                'tags' => null,
            ],
        ]);

        $slow = $this->storage->getSlowQueries(100, 25);
        expect($slow)->toHaveCount(1);

        $notSlow = $this->storage->getSlowQueries(200, 25);
        expect($notSlow)->toHaveCount(0);
    });

    it('prunes old traces', function () {
        $oldContext = [
            'trace_id' => Str::uuid()->toString(),
            'type' => 'request',
            'name' => '/old',
            'status' => 'success',
            'timestamp' => now()->subDays(30)->toDateTimeString(),
            'duration' => 100,
            'memory_peak' => null,
            'user_id' => null,
            'ip' => null,
            'method' => 'GET',
            'url' => null,
            'request_headers' => null,
            'request_body' => null,
            'response_status' => 200,
            'response_headers' => null,
            'tags' => null,
            'environment' => 'testing',
        ];

        $this->storage->storeTrace($oldContext);
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString()));

        $deleted = $this->storage->prune(14);
        expect($deleted)->toBeGreaterThanOrEqual(1);

        $remaining = $this->storage->getTraces([], 25, 0);
        expect($remaining['total'])->toBe(1);
    });

    it('prunes nothing when no old traces', function () {
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString()));

        $deleted = $this->storage->prune(14);
        expect($deleted)->toBe(0);
    });

    it('rejects non-positive prune windows without deleting traces', function () {
        $traceId = Str::uuid()->toString();
        $this->storage->storeTrace(makeTraceContext($traceId));

        expect(fn () => $this->storage->prune(0))->toThrow(InvalidArgumentException::class);
        expect($this->storage->getTrace($traceId))->not->toBeNull();

        expect(fn () => $this->storage->prune(-7))->toThrow(InvalidArgumentException::class);
        expect($this->storage->getTrace($traceId))->not->toBeNull();
    });

    it('gets summary', function () {
        $context = [
            'trace_id' => Str::uuid()->toString(),
            'type' => 'request',
            'name' => '/api/summary',
            'status' => 'success',
            'timestamp' => now()->toDateTimeString(),
            'duration' => 100,
            'memory_peak' => null,
            'user_id' => null,
            'ip' => null,
            'method' => 'GET',
            'url' => null,
            'request_headers' => null,
            'request_body' => null,
            'response_status' => 200,
            'response_headers' => null,
            'tags' => null,
            'environment' => 'testing',
        ];

        $this->storage->storeTrace($context);

        $summary = $this->storage->getSummary('-24 hours');
        expect($summary)->toHaveKey('total_requests');
        expect($summary)->toHaveKey('avg_duration');
        expect($summary)->toHaveKey('total_exceptions');
        expect($summary)->toHaveKey('unresolved_groups');
        expect($summary)->toHaveKey('slow_queries');
        expect($summary)->toHaveKey('failed_jobs');
        expect($summary)->toHaveKey('top_slow_routes');
        expect($summary)->toHaveKey('since');
        expect($summary['total_requests'])->toBeGreaterThanOrEqual(1);
        expect($summary['avg_duration'])->toBe(100.0);
    });

    it('gets summary with zero requests', function () {
        $summary = $this->storage->getSummary('-24 hours');
        expect($summary['total_requests'])->toBe(0);
        expect($summary['avg_duration'])->toBe(0.0);
    });

    it('logs and retrieves audit entries', function () {
        $this->storage->logAudit('test_action', 'user-1', '127.0.0.1', ['key' => 'value']);

        $entries = DB::connection('lookout')
            ->table('lookout_audit_log')
            ->where('action', 'test_action')
            ->get();

        expect($entries)->toHaveCount(1);
        expect($entries[0]->user_id)->toBe('user-1');
        expect($entries[0]->ip)->toBe('127.0.0.1');
        expect($entries[0]->details)->toBe('{"key":"value"}');
    });

    it('logs audit with null optional fields', function () {
        $this->storage->logAudit('bare_action');

        $entry = DB::connection('lookout')
            ->table('lookout_audit_log')
            ->where('action', 'bare_action')
            ->first();

        expect($entry)->not->toBeNull();
        expect($entry->user_id)->toBeNull();
        expect($entry->ip)->toBeNull();
        expect($entry->details)->toBeNull();
    });

    it('gets health info', function () {
        $this->storage->storeTrace(makeTraceContext(Str::uuid()->toString()));

        $health = $this->storage->getHealth();
        expect($health)->toHaveKey('status');
        expect($health)->toHaveKey('storage_size_bytes');
        expect($health)->toHaveKey('storage_size_mb');
        expect($health)->toHaveKey('trace_count');
        expect($health)->toHaveKey('event_count');
        expect($health)->toHaveKey('recent_requests_5m');
        expect($health['status'])->toBe('ok');
        expect($health['trace_count'])->toBeGreaterThanOrEqual(1);
    });
});

describe('SqliteStorage trace ordering', function () {
    beforeEach(function () {
        $this->storage = new SqliteStorage;
    });

    it('returns traces ordered by timestamp descending', function () {
        $id1 = Str::uuid()->toString();
        $id2 = Str::uuid()->toString();

        $this->storage->storeTrace([
            'trace_id' => $id1,
            'type' => 'request',
            'name' => '/first',
            'status' => 'success',
            'timestamp' => now()->subHour()->toDateTimeString(),
            'duration' => 100,
            'memory_peak' => null,
            'user_id' => null,
            'ip' => null,
            'method' => 'GET',
            'url' => null,
            'request_headers' => null,
            'request_body' => null,
            'response_status' => 200,
            'response_headers' => null,
            'tags' => null,
            'environment' => 'testing',
        ]);

        $this->storage->storeTrace([
            'trace_id' => $id2,
            'type' => 'request',
            'name' => '/second',
            'status' => 'success',
            'timestamp' => now()->toDateTimeString(),
            'duration' => 200,
            'memory_peak' => null,
            'user_id' => null,
            'ip' => null,
            'method' => 'GET',
            'url' => null,
            'request_headers' => null,
            'request_body' => null,
            'response_status' => 200,
            'response_headers' => null,
            'tags' => null,
            'environment' => 'testing',
        ]);

        $result = $this->storage->getTraces([], 25, 0);
        expect($result['data'][0]['name'])->toBe('/second');
        expect($result['data'][1]['name'])->toBe('/first');
    });
});
