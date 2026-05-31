<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Zasetsu\Lookout\Storage\DatabaseStorage;
use Zasetsu\Lookout\Storage\StorageContract;

function makeDatabaseStorageTraceContext(string $traceId, array $overrides = []): array
{
    return array_merge([
        'trace_id' => $traceId,
        'type' => 'request',
        'name' => '/api/database-storage',
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

describe('DatabaseStorage', function () {
    beforeEach(function () {
        config([
            'lookout.storage.driver' => env('LOOKOUT_TEST_STORAGE_DRIVER', 'mysql'),
            'lookout.storage.connection' => 'lookout',
        ]);

        $this->storage = new DatabaseStorage;
    });

    it('implements the storage contract with generic database health metrics', function () {
        expect($this->storage)->toBeInstanceOf(StorageContract::class);

        $health = $this->storage->getHealth();

        expect($health['storage_driver'])->toBe(config('lookout.storage.driver'))
            ->and($health['storage_connection'])->toBe('lookout')
            ->and($health['storage_size_bytes'])->toBeNull()
            ->and($health['storage_size_mb'])->toBeNull();
    });

    it('stores and reads traces through the generic SQL implementation', function () {
        $traceId = Str::uuid()->toString();

        $this->storage->storeTrace(makeDatabaseStorageTraceContext($traceId));

        $trace = $this->storage->getTrace($traceId);

        expect($trace)->not->toBeNull()
            ->and($trace['trace_id'])->toBe($traceId)
            ->and($trace['name'])->toBe('/api/database-storage');
    });

    it('claims threshold dispatch slots atomically through the generic SQL implementation', function () {
        $now = now()->toDateTimeString();

        DB::connection('lookout')->table('lookout_thresholds')->insert([
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 1,
            'window_minutes' => 15,
            'channels' => json_encode([]),
            'enabled' => true,
            'last_triggered_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $thresholdId = (int) DB::connection('lookout')->table('lookout_thresholds')->value('id');

        expect($this->storage->claimThresholdDispatchSlot($thresholdId, 15))->toBeTrue()
            ->and($this->storage->claimThresholdDispatchSlot($thresholdId, 15))->toBeFalse();
    });
});
