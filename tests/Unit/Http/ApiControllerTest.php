<?php

use Illuminate\Http\Request;
use Zasetsu\Lookout\Http\Controllers\Api\ApiController;
use Zasetsu\Lookout\Storage\StorageContract;

class ApiControllerStorageFake implements StorageContract
{
    public bool $summaryCalled = false;

    public ?array $traceFilters = null;

    public ?array $exceptionFilters = null;

    public ?int $traceLimit = null;

    public ?int $traceOffset = null;

    public ?int $exceptionLimit = null;

    public ?int $exceptionOffset = null;

    public function storeTrace(array $context): void {}

    public function storeEvents(string $traceId, array $events): void {}

    public function storeTraceBatch(array $context, array $events): void {}

    public function getTraces(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $this->traceFilters = $filters;
        $this->traceLimit = $limit;
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
        $this->exceptionLimit = $limit;
        $this->exceptionOffset = $offset;

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
        $this->summaryCalled = true;

        return ['since' => $since];
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

describe('ApiController', function () {
    it('rejects invalid summary windows before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->summary(Request::create('/lookout/api/summary', 'GET', [
            'since' => 'foo',
        ]));

        expect($response->getStatusCode())->toBe(422)
            ->and($storage->summaryCalled)->toBeFalse();
    });

    it('normalizes valid shorthand summary windows', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->summary(Request::create('/lookout/api/summary', 'GET', [
            'since' => '2h',
        ]));

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['since' => '-2 hours']);
    });

    it('rejects zero shorthand summary windows before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->summary(Request::create('/lookout/api/summary', 'GET', [
            'since' => '0h',
        ]));

        expect($response->getStatusCode())->toBe(422)
            ->and($storage->summaryCalled)->toBeFalse();
    });

    it('rejects negative numeric summary windows before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->summary(Request::create('/lookout/api/summary', 'GET', [
            'since' => '-24',
        ]));

        expect($response->getStatusCode())->toBe(422)
            ->and($storage->summaryCalled)->toBeFalse();
    });

    it('normalizes positive numeric summary windows', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->summary(Request::create('/lookout/api/summary', 'GET', [
            'since' => '24',
        ]));

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['since' => '-24 hours']);
    });

    it('rejects non-scalar request duration filters', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'slower_than' => ['1s'],
        ]));

        expect($response->getStatusCode())->toBe(422)
            ->and($storage->traceFilters)->toBeNull();
    });

    it('rejects non-scalar request filters before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'status' => ['error'],
        ]));

        expect($response->getStatusCode())->toBe(422)
            ->and($storage->traceFilters)->toBeNull();
    });

    it('rejects unsupported API request enum filters before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $statusResponse = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'status' => 'deleted',
        ]));

        $methodResponse = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'method' => 'TRACE',
        ]));

        expect($statusResponse->getStatusCode())->toBe(422)
            ->and($methodResponse->getStatusCode())->toBe(422)
            ->and($storage->traceFilters)->toBeNull();
    });

    it('rejects unsupported API exception status filters before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->exceptions(Request::create('/lookout/api/exceptions', 'GET', [
            'status' => 'closed',
        ]));

        expect($response->getStatusCode())->toBe(422)
            ->and($storage->exceptionFilters)->toBeNull();
    });

    it('rejects non-scalar exception filters before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->exceptions(Request::create('/lookout/api/exceptions', 'GET', [
            'class' => ['RuntimeException'],
        ]));

        expect($response->getStatusCode())->toBe(422)
            ->and($storage->exceptionFilters)->toBeNull();
    });

    it('rejects invalid API pagination values before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $limitResponse = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'limit' => ['100'],
        ]));

        $offsetResponse = $controller->exceptions(Request::create('/lookout/api/exceptions', 'GET', [
            'offset' => 'abc',
        ]));

        expect($limitResponse->getStatusCode())->toBe(422)
            ->and($offsetResponse->getStatusCode())->toBe(422)
            ->and($storage->traceFilters)->toBeNull()
            ->and($storage->exceptionFilters)->toBeNull();
    });

    it('normalizes API pagination boundaries before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'limit' => '999',
            'offset' => '3',
        ]));

        expect($response->getStatusCode())->toBe(200)
            ->and($storage->traceLimit)->toBe(500)
            ->and($storage->traceOffset)->toBe(3);
    });

    it('rejects invalid request duration filters', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'slower_than' => 'abc',
        ]));

        expect($response->getStatusCode())->toBe(422)
            ->and($storage->traceFilters)->toBeNull();
    });

    it('rejects invalid API response status filters before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $stringResponse = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'response_status' => 'abc',
        ]));

        $arrayResponse = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'response_status' => ['500'],
        ]));

        $outOfRangeResponse = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'response_status' => '700',
        ]));

        expect($stringResponse->getStatusCode())->toBe(422)
            ->and($arrayResponse->getStatusCode())->toBe(422)
            ->and($outOfRangeResponse->getStatusCode())->toBe(422)
            ->and($storage->traceFilters)->toBeNull();
    });

    it('normalizes valid API response status filters before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'response_status' => '500',
        ]));

        expect($response->getStatusCode())->toBe(200)
            ->and($storage->traceFilters['response_status'])->toBe(500);
    });

    it('parses valid request duration filters before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'slower_than' => '1.5s',
        ]));

        expect($response->getStatusCode())->toBe(200)
            ->and($storage->traceFilters['min_duration'])->toBe(1500);
    });

    it('accepts supported API enum filters before querying storage', function () {
        $storage = new ApiControllerStorageFake;
        $controller = new ApiController($storage);

        $response = $controller->requests(Request::create('/lookout/api/requests', 'GET', [
            'status' => 'error',
            'method' => 'POST',
        ]));

        expect($response->getStatusCode())->toBe(200)
            ->and($storage->traceFilters['status'])->toBe('error')
            ->and($storage->traceFilters['method'])->toBe('POST');
    });
});
