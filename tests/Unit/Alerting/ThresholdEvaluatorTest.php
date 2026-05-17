<?php

use Illuminate\Support\Facades\DB;
use Zasetsu\Lookout\Alerting\Channels\ChannelContract;
use Zasetsu\Lookout\Alerting\Channels\SlackChannel;
use Zasetsu\Lookout\Alerting\Channels\WebhookChannel;
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

class DispatchingThresholdEvaluator extends ThresholdEvaluator
{
    public function dispatch(object $threshold): void
    {
        $this->dispatchAlert($threshold);
    }
}

class SuccessfulSlackChannel extends SlackChannel
{
    public function send(object $threshold, array $context): void {}
}

class FailingWebhookChannel implements ChannelContract
{
    public function send(object $threshold, array $context): void
    {
        throw new RuntimeException('webhook unavailable');
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

    it('records per-channel alert delivery telemetry in the audit log', function () {
        app()->instance(SlackChannel::class, new SuccessfulSlackChannel);
        app()->instance(WebhookChannel::class, new FailingWebhookChannel);

        $storage = new ThresholdEvaluatorStorageFake;
        $evaluator = new DispatchingThresholdEvaluator($storage);

        $evaluator->dispatch((object) [
            'id' => 7,
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => json_encode(['slack', 'webhook', 'unknown']),
        ]);

        expect($storage->auditLog)->toHaveCount(1)
            ->and($storage->auditLog[0]['action'])->toBe('threshold_triggered')
            ->and($storage->auditLog[0]['details']['deliveries'])->toBe([
                ['channel' => 'slack', 'status' => 'sent'],
                ['channel' => 'webhook', 'status' => 'failed', 'error' => 'webhook unavailable'],
                ['channel' => 'unknown', 'status' => 'skipped'],
            ]);
    });

    it('skips malformed threshold channel payloads without throwing', function () {
        $storage = new ThresholdEvaluatorStorageFake;
        $evaluator = new DispatchingThresholdEvaluator($storage);

        $evaluator->dispatch((object) [
            'id' => 8,
            'name' => 'Malformed channels',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => '"slack"',
        ]);

        expect($storage->auditLog)->toHaveCount(1)
            ->and($storage->auditLog[0]['details']['deliveries'])->toBe([]);
    });
});
