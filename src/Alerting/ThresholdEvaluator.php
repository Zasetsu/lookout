<?php

namespace Zasetsu\Lookout\Alerting;

use Illuminate\Http\Client\RequestException;
use Zasetsu\Lookout\Alerting\Channels\ChannelContract;
use Zasetsu\Lookout\Storage\StorageContract;

class ThresholdEvaluator
{
    public function __construct(
        private StorageContract $storage,
    ) {}

    public function evaluate(): array
    {
        if (! config('lookout.alerting.enabled', false)) {
            return [];
        }

        $thresholds = $this->storage->getEnabledThresholds();

        $triggered = [];

        foreach ($thresholds as $threshold) {
            $threshold = (object) $threshold;

            if ($this->dryRun($threshold)->triggered) {
                if ($this->claimDispatchSlot($threshold)) {
                    $this->dispatchAlert($threshold);
                }
                $triggered[] = $threshold;
            }
        }

        return $triggered;
    }

    public function dryRun(array|object $threshold): ThresholdResult
    {
        $threshold = $this->normalizeThreshold($threshold);
        $windowMinutes = $this->thresholdWindowMinutes($threshold);
        $cooldownMinutes = $this->thresholdCooldownMinutes($threshold);
        $thresholdValue = (float) $threshold->value;
        $currentValue = $this->getMetricValue((string) $threshold->metric, $windowMinutes);

        return new ThresholdResult(
            thresholdId: (int) $threshold->id,
            name: (string) $threshold->name,
            metric: (string) $threshold->metric,
            condition: (string) $threshold->condition,
            thresholdValue: $thresholdValue,
            currentValue: $currentValue,
            windowMinutes: $windowMinutes,
            cooldownMinutes: $cooldownMinutes,
            triggered: $this->matchesCondition($currentValue, (string) $threshold->condition, $thresholdValue),
        );
    }

    /**
     * @return array{dispatched: bool, reason: string, result: array<string, mixed>, deliveries?: array<int, array<string, string|null>>}
     */
    public function dispatchManually(array|object $threshold): array
    {
        $threshold = $this->normalizeThreshold($threshold);
        $result = $this->dryRun($threshold);

        if (! $result->triggered) {
            return [
                'dispatched' => false,
                'reason' => 'condition_not_met',
                'result' => $result->toArray(),
            ];
        }

        $previousLastTriggeredAt = $this->thresholdLastTriggeredAt($threshold);

        if (! $this->claimDispatchSlot($threshold)) {
            return [
                'dispatched' => false,
                'reason' => 'cooldown_active',
                'result' => $result->toArray(),
            ];
        }

        $claimedLastTriggeredAt = $this->claimedLastTriggeredAt($threshold);
        $deliveries = $this->dispatchAlert($threshold, [
            'current_value' => $result->currentValue,
        ]);

        if (! $this->hasSentDelivery($deliveries)) {
            $this->storage->releaseThresholdDispatchSlot((int) $threshold->id, $previousLastTriggeredAt, $claimedLastTriggeredAt);

            return [
                'dispatched' => false,
                'reason' => 'delivery_failed',
                'result' => $result->toArray(),
                'deliveries' => $deliveries,
            ];
        }

        return [
            'dispatched' => true,
            'reason' => 'sent',
            'result' => $result->toArray(),
            'deliveries' => $deliveries,
        ];
    }

    public function dispatchForTesting(array|object $threshold, array $extraContext = []): void
    {
        $threshold = $this->normalizeThreshold($threshold);

        $this->dispatchAlert($threshold, array_merge([
            'test' => true,
            'test_message' => 'Lookout alert channel test',
        ], $extraContext), false);
    }

    protected function evaluateThreshold(object $threshold): bool
    {
        return $this->dryRun($threshold)->triggered;
    }

    protected function claimDispatchSlot(object $threshold): bool
    {
        $cooldown = $this->thresholdCooldownMinutes($threshold);

        return $this->storage->claimThresholdDispatchSlot((int) $threshold->id, $cooldown);
    }

    protected function getMetricValue(string $metric, int $windowMinutes): float
    {
        return $this->storage->getThresholdMetricValue($metric, $windowMinutes);
    }

    /**
     * @return array<int, array{channel: string, status: string, error?: string}>
     */
    protected function dispatchAlert(object $threshold, array $extraContext = [], bool $audit = true): array
    {
        $channels = $this->thresholdChannels($threshold);

        $context = array_merge([
            'threshold_id' => (int) $threshold->id,
            'name' => (string) $threshold->name,
            'metric' => (string) $threshold->metric,
            'condition' => (string) $threshold->condition,
            'value' => (float) $threshold->value,
            'threshold_value' => (float) $threshold->value,
            'window_minutes' => $this->thresholdWindowMinutes($threshold),
            'cooldown_minutes' => $this->thresholdCooldownMinutes($threshold),
        ], $extraContext);
        $deliveries = [];

        foreach ($channels as $channelName) {
            try {
                $channel = $this->resolveChannel($channelName);
                if (! $channel) {
                    $deliveries[] = ['channel' => $channelName, 'status' => 'skipped'];
                } elseif (! $this->channelConfigured($channelName)) {
                    $deliveries[] = ['channel' => $channelName, 'status' => 'skipped'];
                } else {
                    $channel->send($threshold, $context);
                    $deliveries[] = ['channel' => $channelName, 'status' => 'sent'];
                }
            } catch (\Throwable $e) {
                $deliveries[] = [
                    'channel' => $channelName,
                    'status' => 'failed',
                    'error' => $this->safeDeliveryError($e),
                ];

                logger()->warning("Lookout alert channel {$channelName} failed", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $context['deliveries'] = $deliveries;

        if ($audit) {
            $this->storage->logAudit('threshold_triggered', null, null, $context);
        }

        return $deliveries;
    }

    /**
     * @param  array<int, array<string, string|null>>  $deliveries
     */
    protected function hasSentDelivery(array $deliveries): bool
    {
        return collect($deliveries)->contains(fn (array $delivery): bool => $delivery['status'] === 'sent');
    }

    protected function safeDeliveryError(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            return 'HTTP '.$e->response->status().' response from alert channel.';
        }

        return class_basename($e).' while sending alert channel.';
    }

    protected function resolveChannel(string $name): ?ChannelContract
    {
        return match ($name) {
            'email' => app(Channels\EmailChannel::class),
            'slack' => app(Channels\SlackChannel::class),
            'webhook' => app(Channels\WebhookChannel::class),
            default => null,
        };
    }

    protected function channelConfigured(string $name): bool
    {
        return ! empty(config("lookout.alerting.channels.{$name}"));
    }

    /**
     * @return array<int, string>
     */
    protected function thresholdChannels(object $threshold): array
    {
        if (is_array($threshold->channels)) {
            return array_values(array_filter($threshold->channels, is_string(...)));
        }

        $channels = json_decode($threshold->channels, true);

        if (! is_array($channels)) {
            return [];
        }

        return array_values(array_filter($channels, is_string(...)));
    }

    protected function matchesCondition(float $currentValue, string $condition, float $thresholdValue): bool
    {
        return match ($condition) {
            'gt' => $currentValue > $thresholdValue,
            'gte' => $currentValue >= $thresholdValue,
            'lt' => $currentValue < $thresholdValue,
            'lte' => $currentValue <= $thresholdValue,
            'eq' => $currentValue == $thresholdValue,
            default => false,
        };
    }

    protected function normalizeThreshold(array|object $threshold): object
    {
        $threshold = (object) $threshold;

        $threshold->id ??= 0;
        $threshold->name ??= 'Lookout test alert';
        $threshold->metric ??= 'test';
        $threshold->condition ??= 'gte';
        $threshold->value ??= 1;
        $threshold->channels ??= [];

        return $threshold;
    }

    protected function claimedLastTriggeredAt(object $threshold): ?string
    {
        $rule = $this->storage->getThresholdRule((int) $threshold->id);

        return $this->thresholdLastTriggeredAt($rule ?? $threshold);
    }

    protected function thresholdLastTriggeredAt(array|object|null $threshold): ?string
    {
        if ($threshold === null) {
            return null;
        }

        $value = is_array($threshold)
            ? ($threshold['last_triggered_at'] ?? null)
            : ($threshold->last_triggered_at ?? null);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function thresholdWindowMinutes(object $threshold): int
    {
        return (int) ($threshold->window_minutes ?? 15);
    }

    protected function thresholdCooldownMinutes(object $threshold): int
    {
        if (isset($threshold->cooldown_minutes)) {
            return (int) $threshold->cooldown_minutes;
        }

        if (isset($threshold->window_minutes)) {
            return (int) $threshold->window_minutes;
        }

        return 15;
    }
}
