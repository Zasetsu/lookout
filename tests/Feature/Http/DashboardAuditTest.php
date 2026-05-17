<?php

use Illuminate\Http\Request;
use Zasetsu\Lookout\Http\Controllers\Dashboard\DashboardController;
use Zasetsu\Lookout\Storage\StorageContract;

class DashboardAuditStorageFake implements StorageContract
{
    public array $auditLog = [];

    public array $resolvedGroups = [];

    public array $ignoredGroups = [];

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
        $this->resolvedGroups[] = $groupId;

        return true;
    }

    public function ignoreExceptionGroup(int $groupId): bool
    {
        $this->ignoredGroups[] = $groupId;

        return true;
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

describe('DashboardController audit logging', function () {
    beforeEach(function () {
        $request = Request::create('/lookout/exceptions/42', 'POST', server: [
            'REMOTE_ADDR' => '10.0.0.5',
        ]);

        app()->instance('request', $request);
    });

    it('audits resolved exception groups', function () {
        $storage = new DashboardAuditStorageFake;
        $controller = new DashboardController($storage);

        $controller->resolveException(42);

        expect($storage->resolvedGroups)->toBe([42])
            ->and($storage->auditLog)->toHaveCount(1)
            ->and($storage->auditLog[0]['action'])->toBe('exception_group_resolved')
            ->and($storage->auditLog[0]['ip'])->toBe('10.0.0.5')
            ->and($storage->auditLog[0]['details'])->toBe(['group_id' => 42]);
    });

    it('audits ignored exception groups', function () {
        $storage = new DashboardAuditStorageFake;
        $controller = new DashboardController($storage);

        $controller->ignoreException(42);

        expect($storage->ignoredGroups)->toBe([42])
            ->and($storage->auditLog)->toHaveCount(1)
            ->and($storage->auditLog[0]['action'])->toBe('exception_group_ignored')
            ->and($storage->auditLog[0]['ip'])->toBe('10.0.0.5')
            ->and($storage->auditLog[0]['details'])->toBe(['group_id' => 42]);
    });
});
