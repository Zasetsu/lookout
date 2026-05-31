<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Recorders\ExceptionRecorder;
use Zasetsu\Lookout\Storage\StorageContract;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

class ExceptionRecorderStorageFake implements StorageContract
{
    public int $storedBatches = 0;

    public int $upsertedGroups = 0;

    public function storeTrace(array $context): void {}

    public function storeEvents(string $traceId, array $events): void {}

    public function storeTraceBatch(array $context, array $events): void
    {
        $this->storedBatches++;
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

    public function upsertExceptionGroup(string $fingerprint, array $data): void
    {
        $this->upsertedGroups++;
    }

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

describe('ExceptionRecorder', function () {
    it('defers sync persistence when a trace context is already active', function () {
        config(['lookout.ingestion.sync_exceptions' => true]);

        $buffer = new TraceBuffer;
        $context = new ExecutionContext;
        $context->type = 'request';
        $context->name = 'GET /fails';

        $buffer->setContext($context);
        $buffer->markSampled();

        $storage = new ExceptionRecorderStorageFake;

        $recorder = new ExceptionRecorder($buffer, new Redactor, $storage);
        $recorder->handleException(new RuntimeException('failure before response finalizer'));

        expect($storage->upsertedGroups)->toBe(1);
        expect($storage->storedBatches)->toBe(0);
        expect($buffer->getContext())->toBe($context);
        expect($buffer->getEvents())->toHaveCount(1);
        expect($buffer->getEvents()[0]->eventType)->toBe('exception');
        expect($context->status)->toBe('error');
    });

    it('uses the sanitized request URL in exception payloads', function () {
        $request = Request::create('https://app.test/invite/super-secret-token', 'GET');
        $request->setRouteResolver(fn () => new Route(['GET'], 'invite/{token}', fn () => null));
        app()->instance('request', $request);

        $buffer = new TraceBuffer;
        $context = ExecutionContext::forRequest($request);
        $buffer->setContext($context);
        $buffer->markSampled();

        $recorder = new ExceptionRecorder($buffer, new Redactor, new ExceptionRecorderStorageFake);
        $recorder->handleException(new RuntimeException('failure'));

        expect($buffer->getEvents()[0]->payload['url'])->toBe('https://app.test/invite/{token}')
            ->and($buffer->getEvents()[0]->payload['url'])->not->toContain('super-secret-token');
    });
});
