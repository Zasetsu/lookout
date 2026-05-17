<?php

use Illuminate\Support\Facades\Log;
use Mockery\Mock;
use Zasetsu\Lookout\Storage\StorageContract;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

class FailingIngestStorageFake implements StorageContract
{
    public function storeTrace(array $context): void {}

    public function storeEvents(string $traceId, array $events): void {}

    public function storeTraceBatch(array $context, array $events): void
    {
        throw new RuntimeException('ingest storage unavailable');
    }

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

    public function logAudit(string $action, ?string $userId = null, ?string $ip = null, ?array $details = null): void {}

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

describe('LookoutServiceProvider', function () {
    it('drops terminating traces when ingest dispatch fails', function () {
        config([
            'queue.default' => 'sync',
            'lookout.ingestion.connection' => 'sync',
        ]);

        Log::spy();
        app()->instance(StorageContract::class, new FailingIngestStorageFake);

        $buffer = app(TraceBuffer::class);
        $context = new ExecutionContext;
        $context->type = 'request';
        $context->name = 'GET /fails';
        $traceId = $context->traceId;
        $buffer->setContext($context);
        $buffer->markSampled();

        expect(fn () => app()->terminate())->not->toThrow(RuntimeException::class);

        /** @var Mock $logger */
        $logger = Log::getFacadeRoot();

        $logger->shouldHaveReceived('warning', fn (string $message, array $logContext): bool => $message === 'Lookout trace dispatch failed'
            && ($logContext['trace_id'] ?? null) === $traceId
            && ($logContext['error'] ?? null) === 'ingest storage unavailable');
    });
});
