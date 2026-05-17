<?php

namespace Zasetsu\Lookout\Alerting;

use Illuminate\Support\Facades\DB;
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

        $thresholds = DB::connection(config('lookout.storage.connection', 'lookout'))
            ->table('lookout_thresholds')
            ->where('enabled', true)
            ->get();

        $triggered = [];

        foreach ($thresholds as $threshold) {
            if ($this->evaluateThreshold($threshold)) {
                if ($this->claimDispatchSlot($threshold)) {
                    $this->dispatchAlert($threshold);
                }
                $triggered[] = $threshold;
            }
        }

        return $triggered;
    }

    protected function evaluateThreshold(object $threshold): bool
    {
        $value = $this->getMetricValue($threshold->metric, $threshold->window_minutes);

        return match ($threshold->condition) {
            'gt' => $value > $threshold->value,
            'gte' => $value >= $threshold->value,
            'lt' => $value < $threshold->value,
            'lte' => $value <= $threshold->value,
            'eq' => $value == $threshold->value,
            default => false,
        };
    }

    protected function claimDispatchSlot(object $threshold): bool
    {
        $window = (int) ($threshold->window_minutes ?? 15);
        $cooldown = now()->subMinutes(max($window, 15))->toDateTimeString();
        $claimedAt = now()->toDateTimeString();

        return DB::connection(config('lookout.storage.connection', 'lookout'))
            ->table('lookout_thresholds')
            ->where('id', $threshold->id)
            ->where(function ($query) use ($cooldown) {
                $query->whereNull('last_triggered_at')
                    ->orWhere('last_triggered_at', '<', $cooldown);
            })
            ->update([
                'last_triggered_at' => $claimedAt,
                'updated_at' => $claimedAt,
            ]) > 0;
    }

    protected function getMetricValue(string $metric, int $windowMinutes): float
    {
        $connection = config('lookout.storage.connection', 'lookout');
        $since = now()->subMinutes($windowMinutes)->toDateTimeString();

        return match ($metric) {
            'request_duration' => (float) (DB::connection($connection)
                ->table('lookout_traces')
                ->where('type', 'request')
                ->where('timestamp', '>=', $since)
                ->avg('duration') ?? 0),
            'exception_count' => (float) DB::connection($connection)
                ->table('lookout_events')
                ->where('event_type', 'exception')
                ->where('timestamp', '>=', $since)
                ->count(),
            'slow_query_count' => (float) DB::connection($connection)
                ->table('lookout_events')
                ->where('event_type', 'query')
                ->where('duration', '>=', 500)
                ->where('timestamp', '>=', $since)
                ->count(),
            'failed_job_count' => (float) DB::connection($connection)
                ->table('lookout_events')
                ->where('event_type', 'job_failed')
                ->where('timestamp', '>=', $since)
                ->count(),
            default => 0.0,
        };
    }

    protected function dispatchAlert(object $threshold): void
    {
        $channels = $this->thresholdChannels($threshold);

        $context = [
            'threshold_id' => $threshold->id,
            'name' => $threshold->name,
            'metric' => $threshold->metric,
            'condition' => $threshold->condition,
            'value' => $threshold->value,
            'window_minutes' => $threshold->window_minutes,
        ];
        $deliveries = [];

        foreach ($channels as $channelName) {
            try {
                $channel = $this->resolveChannel($channelName);
                if ($channel) {
                    $channel->send($threshold, $context);
                    $deliveries[] = ['channel' => $channelName, 'status' => 'sent'];
                } else {
                    $deliveries[] = ['channel' => $channelName, 'status' => 'skipped'];
                }
            } catch (\Throwable $e) {
                $deliveries[] = [
                    'channel' => $channelName,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                logger()->warning("Lookout alert channel {$channelName} failed", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $context['deliveries'] = $deliveries;

        $this->storage->logAudit('threshold_triggered', null, null, $context);
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

    /**
     * @return array<int, string>
     */
    protected function thresholdChannels(object $threshold): array
    {
        $channels = json_decode($threshold->channels, true);

        if (! is_array($channels)) {
            return [];
        }

        return array_values(array_filter($channels, is_string(...)));
    }
}
