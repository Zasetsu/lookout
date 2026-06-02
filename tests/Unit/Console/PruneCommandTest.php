<?php

use Illuminate\Support\Facades\Artisan;
use Zasetsu\Lookout\Storage\StorageContract;

class PruneCommandStorageFake implements StorageContract
{
    public ?int $prunedDays = null;

    public array $auditLog = [];

    public function storeTrace(array $context): void {}

    public function storeEvents(string $traceId, array $events): void {}

    public function storeTraceBatch(array $context, array $events): void {}

    public function getTraces(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        return ['data' => [], 'total' => 0];
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
        return ['data' => [], 'total' => 0];
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
        $this->prunedDays = $olderThanDays;

        return 3;
    }

    public function upsertExceptionGroup(string $fingerprint, array $data): void {}

    public function logAudit(string $action, ?string $userId = null, ?string $ip = null, ?array $details = null): void
    {
        $this->auditLog[] = compact('action', 'userId', 'ip', 'details');
    }

    public function getAuditLog(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return ['data' => [], 'total' => 0];
    }

    public function getHealth(): array
    {
        return [];
    }

    public function getPayloadBudgetStats(): array
    {
        return [];
    }

    public function upsertDeployMarker(array $attributes): array
    {
        return ['marker' => $attributes, 'created' => true];
    }

    public function getDeployMarkers(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return ['data' => [], 'total' => 0];
    }

    public function getLatestDeployMarker(?string $environment = null): ?array
    {
        return null;
    }

    public function getDeployMarkersBetween(string $from, string $to, ?string $environment = null): array
    {
        return [];
    }

    public function getEnabledThresholds(): array
    {
        return [];
    }

    public function getThresholdRules(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return ['data' => [], 'total' => 0];
    }

    public function getThresholdRule(int $ruleId): ?array
    {
        return null;
    }

    public function createThresholdRule(array $attributes): array
    {
        return $attributes;
    }

    public function updateThresholdRule(int $ruleId, array $attributes): array
    {
        return $attributes;
    }

    public function setThresholdRuleEnabled(int $ruleId, bool $enabled): array
    {
        return ['enabled' => $enabled];
    }

    public function deleteThresholdRule(int $ruleId): bool
    {
        return false;
    }

    public function getThresholdMetricValue(string $metric, int $windowMinutes): float
    {
        return 0.0;
    }

    public function claimThresholdDispatchSlot(int $thresholdId, int $cooldownMinutes): bool
    {
        return false;
    }

    public function releaseThresholdDispatchSlot(int $thresholdId, ?string $previousLastTriggeredAt, ?string $expectedLastTriggeredAt = null): void {}

    public function getEventsByType(string $eventType, array $filters = [], int $limit = 25, int $offset = 0): array
    {
        return ['data' => [], 'total' => 0];
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

describe('PruneCommand', function () {
    it('rejects non-positive days before calling storage', function () {
        $storage = new PruneCommandStorageFake;
        app()->instance(StorageContract::class, $storage);

        $exitCode = Artisan::call('lookout:prune', ['--days' => 0]);

        expect($exitCode)->toBe(1)
            ->and(Artisan::output())->toContain('Retention days must be greater than zero.')
            ->and($storage->prunedDays)->toBeNull()
            ->and($storage->auditLog)->toBe([]);
    });

    it('prunes and audits valid retention windows', function () {
        $storage = new PruneCommandStorageFake;
        app()->instance(StorageContract::class, $storage);

        $exitCode = Artisan::call('lookout:prune', ['--days' => 7]);

        expect($exitCode)->toBe(0)
            ->and($storage->prunedDays)->toBe(7)
            ->and($storage->auditLog[0]['action'])->toBe('prune_run')
            ->and($storage->auditLog[0]['details'])->toBe([
                'days' => 7,
                'deleted_traces' => 3,
            ]);
    });
});
