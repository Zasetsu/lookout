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

function makeDatabaseThresholdRuleAttributes(array $overrides = []): array
{
    return array_merge([
        'name' => 'Database high exceptions',
        'metric' => 'exception_count',
        'condition' => 'gte',
        'value' => 3,
        'window_minutes' => 15,
        'cooldown_minutes' => 45,
        'channels' => ['mail', 'webhook'],
        'enabled' => true,
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

    it('persists threshold rule CRUD through the generic SQL implementation', function () {
        $created = $this->storage->createThresholdRule(makeDatabaseThresholdRuleAttributes());

        expect($created['channels'])->toBe(['mail', 'webhook'])
            ->and($created['enabled'])->toBeTrue()
            ->and($created['cooldown_minutes'])->toBe(45);

        $listed = $this->storage->getThresholdRules(['enabled' => true], 10, 0);

        expect($listed['total'])->toBe(1)
            ->and($listed['data'][0]['id'])->toBe($created['id']);

        $updated = $this->storage->updateThresholdRule($created['id'], [
            'channels' => ['slack'],
            'enabled' => false,
            'cooldown_minutes' => 5,
        ]);

        expect($updated['channels'])->toBe(['slack'])
            ->and($updated['enabled'])->toBeFalse()
            ->and($updated['cooldown_minutes'])->toBe(5);

        expect($this->storage->setThresholdRuleEnabled($created['id'], true)['enabled'])->toBeTrue()
            ->and($this->storage->deleteThresholdRule($created['id']))->toBeTrue()
            ->and($this->storage->getThresholdRule($created['id']))->toBeNull();
    });

    it('uses threshold cooldown minutes as the dispatch slot window', function () {
        $rule = $this->storage->createThresholdRule(makeDatabaseThresholdRuleAttributes([
            'cooldown_minutes' => 45,
        ]));

        expect($this->storage->claimThresholdDispatchSlot($rule['id'], 45))->toBeTrue();

        DB::connection('lookout')
            ->table('lookout_thresholds')
            ->where('id', $rule['id'])
            ->update([
                'last_triggered_at' => now()->subMinutes(44)->toDateTimeString(),
            ]);

        expect($this->storage->claimThresholdDispatchSlot($rule['id'], 45))->toBeFalse();

        DB::connection('lookout')
            ->table('lookout_thresholds')
            ->where('id', $rule['id'])
            ->update([
                'last_triggered_at' => now()->subMinutes(46)->toDateTimeString(),
            ]);

        expect($this->storage->claimThresholdDispatchSlot($rule['id'], 45))->toBeTrue();
    });
});
