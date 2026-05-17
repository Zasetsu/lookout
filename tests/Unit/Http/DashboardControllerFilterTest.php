<?php

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Zasetsu\Lookout\Http\Controllers\Dashboard\DashboardController;
use Zasetsu\Lookout\Storage\StorageContract;

class DashboardFilterStorageFake implements StorageContract
{
    public ?array $traceFilters = null;

    public ?array $exceptionFilters = null;

    public ?int $slowQueryThreshold = null;

    public ?int $traceOffset = null;

    public function storeTrace(array $context): void {}

    public function storeEvents(string $traceId, array $events): void {}

    public function storeTraceBatch(array $context, array $events): void {}

    public function getTraces(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $this->traceFilters = $filters;
        $this->traceOffset = $offset;

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
        $this->exceptionFilters = $filters;

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
        $this->slowQueryThreshold = $threshold;

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

    public function logAudit(string $action, ?string $userId = null, ?string $ip = null, ?array $details = null): void {}

    public function getHealth(): array
    {
        return [];
    }

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
        return ['distribution' => [], 'total' => 0];
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

function expectDashboardFilterRejection(callable $callback): void
{
    try {
        $callback();
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);

        return;
    }

    throw new RuntimeException('Expected dashboard filter validation to reject the request.');
}

describe('DashboardController filter validation', function () {
    it('rejects non-scalar request filters before querying storage', function () {
        $storage = new DashboardFilterStorageFake;
        $controller = new DashboardController($storage);

        expectDashboardFilterRejection(fn () => $controller->requests(Request::create('/lookout/requests', 'GET', [
            'status' => ['error'],
        ])));

        expect($storage->traceFilters)->toBeNull();
    });

    it('rejects non-scalar exception filters before querying storage', function () {
        $storage = new DashboardFilterStorageFake;
        $controller = new DashboardController($storage);

        expectDashboardFilterRejection(fn () => $controller->exceptions(Request::create('/lookout/exceptions', 'GET', [
            'class' => ['RuntimeException'],
        ])));

        expect($storage->exceptionFilters)->toBeNull();
    });

    it('rejects non-scalar query thresholds before querying storage', function () {
        $storage = new DashboardFilterStorageFake;
        $controller = new DashboardController($storage);

        expectDashboardFilterRejection(fn () => $controller->queries(Request::create('/lookout/queries', 'GET', [
            'threshold' => ['500'],
        ])));

        expect($storage->slowQueryThreshold)->toBeNull();
    });

    it('rejects invalid numeric dashboard inputs before querying storage', function () {
        $storage = new DashboardFilterStorageFake;
        $controller = new DashboardController($storage);

        expectDashboardFilterRejection(fn () => $controller->queries(Request::create('/lookout/queries', 'GET', [
            'threshold' => 'abc',
        ])));

        expectDashboardFilterRejection(fn () => $controller->requests(Request::create('/lookout/requests', 'GET', [
            'page' => ['2'],
        ])));

        expectDashboardFilterRejection(fn () => $controller->requests(Request::create('/lookout/requests', 'GET', [
            'min_duration' => 'abc',
        ])));

        expectDashboardFilterRejection(fn () => $controller->requests(Request::create('/lookout/requests', 'GET', [
            'response_status' => 'abc',
        ])));

        expectDashboardFilterRejection(fn () => $controller->requests(Request::create('/lookout/requests', 'GET', [
            'response_status' => '700',
        ])));

        expect($storage->slowQueryThreshold)->toBeNull()
            ->and($storage->traceFilters)->toBeNull();
    });

    it('normalizes valid numeric dashboard inputs before querying storage', function () {
        $storage = new DashboardFilterStorageFake;
        $controller = new DashboardController($storage);

        $controller->queries(Request::create('/lookout/queries', 'GET', [
            'threshold' => '250',
        ]));

        $controller->requests(Request::create('/lookout/requests', 'GET', [
            'page' => '3',
            'min_duration' => '150',
            'response_status' => '500',
        ]));

        expect($storage->slowQueryThreshold)->toBe(250)
            ->and($storage->traceOffset)->toBe(50)
            ->and($storage->traceFilters['min_duration'])->toBe(150)
            ->and($storage->traceFilters['response_status'])->toBe(500);
    });
});
