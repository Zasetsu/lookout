<?php

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Zasetsu\Lookout\Http\Controllers\Dashboard\DashboardController;
use Zasetsu\Lookout\Storage\StorageContract;

class DashboardAuditStorageFake implements StorageContract
{
    public array $auditLog = [];

    public array $auditEntries = [];

    public ?array $auditFilters = null;

    public ?int $auditLimit = null;

    public ?int $auditOffset = null;

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

    public function getAuditLog(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $this->auditFilters = $filters;
        $this->auditLimit = $limit;
        $this->auditOffset = $offset;

        return ['data' => $this->auditEntries, 'total' => count($this->auditEntries)];
    }

    public function getHealth(): array
    {
        return [];
    }

    public function getPayloadBudgetStats(): array
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

function expectDashboardAuditRejection(callable $callback): void
{
    try {
        $callback();
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);

        return;
    }

    throw new RuntimeException('Expected dashboard audit validation to reject the request.');
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

    it('passes audit filters and pagination to storage', function () {
        $storage = new DashboardAuditStorageFake;
        $controller = new DashboardController($storage);

        $controller->audit(Request::create('/lookout/audit', 'GET', [
            'action' => 'threshold_triggered',
            'since' => '2026-05-17 00:00:00',
            'page' => '3',
        ]));

        expect($storage->auditFilters)->toBe([
            'action' => 'threshold_triggered',
            'since' => '2026-05-17 00:00:00',
        ])
            ->and($storage->auditLimit)->toBe(50)
            ->and($storage->auditOffset)->toBe(100);
    });

    it('rejects non-scalar audit filters before querying storage', function () {
        $storage = new DashboardAuditStorageFake;
        $controller = new DashboardController($storage);

        expectDashboardAuditRejection(fn () => $controller->audit(Request::create('/lookout/audit', 'GET', [
            'action' => ['threshold_triggered'],
        ])));

        expect($storage->auditFilters)->toBeNull();
    });

    it('exports audit entries as csv and json', function () {
        $storage = new DashboardAuditStorageFake;
        $storage->auditEntries = [[
            'created_at' => '2026-05-17 12:00:00',
            'action' => 'threshold_triggered',
            'user_id' => null,
            'ip' => null,
            'details' => '{"name":"High exceptions"}',
        ]];
        $controller = new DashboardController($storage);

        $csv = $controller->exportAudit(Request::create('/lookout/audit/export', 'GET', [
            'format' => 'csv',
        ]));
        $json = $controller->exportAudit(Request::create('/lookout/audit/export', 'GET', [
            'format' => 'json',
        ]));

        expect($csv->headers->get('Content-Type'))->toContain('text/csv')
            ->and($csv->getContent())->toContain('created_at,action,user_id,ip,details')
            ->and($csv->getContent())->toContain('threshold_triggered')
            ->and($json->getData(true)['meta']['total'])->toBe(1)
            ->and($json->getData(true)['data'][0]['action'])->toBe('threshold_triggered');
    });
});
