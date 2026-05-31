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

    public array $metricValues = [
        'exception_count' => 1.0,
    ];

    public array $claimedCooldowns = [];

    public array $claimResults = [];

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
        return DB::connection('lookout')
            ->table('lookout_thresholds')
            ->where('enabled', true)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
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
        return (float) ($this->metricValues[$metric] ?? 0.0);
    }

    public function claimThresholdDispatchSlot(int $thresholdId, int $cooldownMinutes): bool
    {
        $this->claimedCooldowns[] = $cooldownMinutes;

        if ($this->claimResults !== []) {
            return (bool) array_shift($this->claimResults);
        }

        $cooldown = now()->subMinutes(max($cooldownMinutes, 0))->toDateTimeString();
        $claimedAt = now()->toDateTimeString();

        return DB::connection('lookout')
            ->table('lookout_thresholds')
            ->where('id', $thresholdId)
            ->where(function ($query) use ($cooldown) {
                $query->whereNull('last_triggered_at')
                    ->orWhere('last_triggered_at', '<', $cooldown);
            })
            ->update([
                'last_triggered_at' => $claimedAt,
                'updated_at' => $claimedAt,
            ]) > 0;
    }

    public function releaseThresholdDispatchSlot(int $thresholdId, ?string $previousLastTriggeredAt, ?string $expectedLastTriggeredAt = null): void
    {
        $query = DB::connection('lookout')
            ->table('lookout_thresholds')
            ->where('id', $thresholdId);

        if ($expectedLastTriggeredAt !== null) {
            $query->where('last_triggered_at', $expectedLastTriggeredAt);
        }

        $query->update([
            'last_triggered_at' => $previousLastTriggeredAt,
            'updated_at' => now()->toDateTimeString(),
        ]);
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

beforeEach(function () {
    config([
        'lookout.alerting.channels.email' => 'ops@example.test',
        'lookout.alerting.channels.slack' => 'https://hooks.slack.test/services/test',
        'lookout.alerting.channels.webhook' => 'https://alerts.example.test/lookout',
    ]);
});

describe('ThresholdEvaluator', function () {
    it('returns a threshold result for dry run evaluations without dispatching', function () {
        $storage = new ThresholdEvaluatorStorageFake;
        $storage->metricValues['error_rate'] = 12.5;

        $result = (new ThresholdEvaluator($storage))->dryRun([
            'id' => 12,
            'name' => 'Elevated errors',
            'metric' => 'error_rate',
            'condition' => 'gte',
            'value' => 10,
            'window_minutes' => 5,
            'cooldown_minutes' => 20,
            'channels' => ['slack'],
        ]);

        expect($result->toArray())->toBe([
            'threshold_id' => 12,
            'name' => 'Elevated errors',
            'metric' => 'error_rate',
            'condition' => 'gte',
            'threshold_value' => 10.0,
            'current_value' => 12.5,
            'window_minutes' => 5,
            'cooldown_minutes' => 20,
            'triggered' => true,
        ])->and($storage->auditLog)->toBe([]);
    });

    it('returns condition_not_met for manual dispatch when dry run does not trigger', function () {
        $storage = new ThresholdEvaluatorStorageFake;
        $storage->metricValues['exception_count'] = 1.0;

        $result = (new ThresholdEvaluator($storage))->dispatchManually((object) [
            'id' => 13,
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => ['slack'],
        ]);

        expect($result['dispatched'])->toBeFalse()
            ->and($result['reason'])->toBe('condition_not_met')
            ->and($storage->claimedCooldowns)->toBe([]);
    });

    it('returns cooldown_active for manual dispatch when the cooldown claim is unavailable', function () {
        $storage = new ThresholdEvaluatorStorageFake;
        $storage->metricValues['exception_count'] = 6.0;
        $storage->claimResults = [false];

        $result = (new ThresholdEvaluator($storage))->dispatchManually((object) [
            'id' => 14,
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => ['slack'],
        ]);

        expect($result['dispatched'])->toBeFalse()
            ->and($result['reason'])->toBe('cooldown_active');
    });

    it('dispatches manually when the condition matches and cooldown claim succeeds', function () {
        app()->instance(SlackChannel::class, new SuccessfulSlackChannel);

        $storage = new ThresholdEvaluatorStorageFake;
        $storage->metricValues['exception_count'] = 6.0;
        $storage->claimResults = [true];

        $result = (new ThresholdEvaluator($storage))->dispatchManually((object) [
            'id' => 15,
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => ['slack'],
        ]);

        expect($result['dispatched'])->toBeTrue()
            ->and($result['reason'])->toBe('sent')
            ->and($storage->auditLog)->toHaveCount(1)
            ->and($storage->auditLog[0]['action'])->toBe('threshold_triggered');
    });

    it('reports delivery_failed for manual dispatch when no channel sends', function () {
        app()->instance(WebhookChannel::class, new FailingWebhookChannel);

        $storage = new ThresholdEvaluatorStorageFake;
        $storage->metricValues['exception_count'] = 6.0;
        $storage->claimResults = [true];

        $result = (new ThresholdEvaluator($storage))->dispatchManually((object) [
            'id' => 15,
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => ['webhook'],
        ]);

        expect($result['dispatched'])->toBeFalse()
            ->and($result['reason'])->toBe('delivery_failed')
            ->and($result['deliveries'])->toBe([
                ['channel' => 'webhook', 'status' => 'failed', 'error' => 'RuntimeException while sending alert channel.'],
            ]);
    });

    it('does not leave a failed manual dispatch inside cooldown', function () {
        app()->instance(WebhookChannel::class, new FailingWebhookChannel);

        $storage = new ThresholdEvaluatorStorageFake;
        $storage->metricValues['exception_count'] = 6.0;

        $now = now()->toDateTimeString();
        $rule = [
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'cooldown_minutes' => 15,
            'channels' => json_encode(['webhook']),
            'enabled' => true,
            'last_triggered_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $id = DB::connection('lookout')->table('lookout_thresholds')->insertGetId($rule);

        $result = (new ThresholdEvaluator($storage))->dispatchManually((object) array_merge($rule, [
            'id' => $id,
            'channels' => ['webhook'],
        ]));

        $row = DB::connection('lookout')->table('lookout_thresholds')->where('id', $id)->first();

        expect($result['dispatched'])->toBeFalse()
            ->and($result['reason'])->toBe('delivery_failed')
            ->and($row->last_triggered_at)->toBeNull();
    });

    it('skips unconfigured channels instead of reporting them as sent', function () {
        config(['lookout.alerting.channels.slack' => null]);
        app()->instance(SlackChannel::class, new SuccessfulSlackChannel);

        $storage = new ThresholdEvaluatorStorageFake;
        $evaluator = new DispatchingThresholdEvaluator($storage);

        $evaluator->dispatch((object) [
            'id' => 18,
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => ['slack'],
        ]);

        expect($storage->auditLog[0]['details']['deliveries'])->toBe([
            ['channel' => 'slack', 'status' => 'skipped'],
        ]);
    });

    it('does not persist raw channel exception messages in audit delivery details', function () {
        app()->instance(WebhookChannel::class, new FailingWebhookChannel);

        $storage = new ThresholdEvaluatorStorageFake;
        $evaluator = new DispatchingThresholdEvaluator($storage);

        $evaluator->dispatch((object) [
            'id' => 17,
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => ['webhook'],
        ]);

        expect($storage->auditLog[0]['details']['deliveries'][0]['error'])
            ->toBe('RuntimeException while sending alert channel.');
        expect($storage->auditLog[0]['details']['deliveries'][0]['error'])
            ->not->toContain('webhook unavailable');
    });

    it('dispatches for testing with extra context without writing threshold triggered audit logs', function () {
        app()->instance(SlackChannel::class, new SuccessfulSlackChannel);

        $storage = new ThresholdEvaluatorStorageFake;

        (new ThresholdEvaluator($storage))->dispatchForTesting((object) [
            'id' => 16,
            'name' => 'High exceptions',
            'metric' => 'exception_count',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 15,
            'channels' => ['slack'],
        ], ['test' => true]);

        expect($storage->auditLog)->toBe([]);
    });

    it('uses cooldown minutes, then window minutes, then fifteen minutes for dispatch claims', function () {
        $storage = new ThresholdEvaluatorStorageFake;
        $storage->metricValues['exception_count'] = 6.0;
        $storage->claimResults = [true, true, true];

        $evaluator = new ThresholdEvaluator($storage);

        foreach ([
            ['id' => 21, 'cooldown_minutes' => 3, 'window_minutes' => 8],
            ['id' => 22, 'window_minutes' => 8],
            ['id' => 23],
        ] as $threshold) {
            $evaluator->dispatchManually((object) array_merge([
                'name' => 'High exceptions',
                'metric' => 'exception_count',
                'condition' => 'gte',
                'value' => 5,
                'channels' => [],
            ], $threshold));
        }

        expect($storage->claimedCooldowns)->toBe([3, 8, 15]);
    });

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
                ['channel' => 'webhook', 'status' => 'failed', 'error' => 'RuntimeException while sending alert channel.'],
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
