<?php

use Illuminate\Support\Facades\DB;
use Zasetsu\Lookout\Alerting\ThresholdEvaluator;
use Zasetsu\Lookout\Storage\StorageContract;

class ThresholdEvaluatorStorageFake implements StorageContract
{
    public array $auditLog = [];

    public function storeTrace(array $context): void {}

    public function storeEvents(string $traceId, array $events): void {}

    public function storeTraceBatch(array $context, array $events): void {}

    public function getTraces(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        return [];
    }

    public function getTrace(string $traceId): ?array
    {
        return null;
    }

    public function getEvents(string $traceId): array
    {
        return [];
    }

    public function getExceptionGroups(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        return [];
    }

    public function getExceptionGroupStatusCounts(): array
    {
        return [];
    }

    public function getExceptionGroup(int $groupId): ?array
    {
        return null;
    }

    public function resolveExceptionGroup(int $groupId): bool
    {
        return false;
    }

    public function ignoreExceptionGroup(int $groupId): bool
    {
        return false;
    }

    public function getSlowQueries(int $threshold = 500, int $limit = 25): array
    {
        return [];
    }

    public function getSummary(string $since = '-24 hours'): array
    {
        return [];
    }

    public function prune(int $olderThanDays = 14): int
    {
        return 0;
    }

    public function upsertExceptionGroup(string $fingerprint, array $data): void {}

    public function logAudit(string $action, ?string $userId = null, ?string $ip = null, ?array $details = null): void
    {
        $this->auditLog[] = compact('action', 'userId', 'ip', 'details');
    }

    public function getHealth(): array
    {
        return [];
    }

    public function getEventsByType(string $eventType, array $filters = [], int $limit = 25, int $offset = 0): array
    {
        return [];
    }

    public function getCacheStats(string $since = '-24 hours'): array
    {
        return [];
    }

    public function getRequestVolumeByHour(string $since = '-24 hours'): array
    {
        return [];
    }

    public function getStatusDistribution(string $since = '-24 hours'): array
    {
        return [];
    }

    public function getTopExceptions(int $limit = 5): array
    {
        return [];
    }

    public function getEventsByHour(string $eventType, string $since = '-24 hours'): array
    {
        return [];
    }

    public function getQueryDurationBuckets(int $limit = 50): array
    {
        return [];
    }

    public function getTotalEventsCount(string $eventType): int
    {
        return 0;
    }
}

class ClaimableThresholdEvaluator extends ThresholdEvaluator
{
    public function claim(object $threshold): bool
    {
        return $this->claimDispatchSlot($threshold);
    }
}

describe('ThresholdEvaluator', function () {
    it('atomically claims a threshold cooldown slot once', function () {
        config(['lookout.storage.connection' => 'lookout']);

        DB::connection('lookout')->table('lookout_thresholds')->insert([
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 1,
            'window_minutes' => 15,
            'channels' => json_encode([]),
            'enabled' => true,
            'last_triggered_at' => null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $threshold = DB::connection('lookout')->table('lookout_thresholds')->first();
        $evaluator = new ClaimableThresholdEvaluator(new ThresholdEvaluatorStorageFake);

        expect($evaluator->claim($threshold))->toBeTrue()
            ->and($evaluator->claim($threshold))->toBeFalse();
    });
});
