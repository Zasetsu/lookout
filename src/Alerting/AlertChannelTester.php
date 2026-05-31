<?php

namespace Zasetsu\Lookout\Alerting;

use Illuminate\Http\Client\RequestException;
use Zasetsu\Lookout\Alerting\Channels\ChannelContract;

class AlertChannelTester
{
    /**
     * @return array{channel: string, status: 'sent'|'failed', error: string|null}
     */
    public function test(string $channel, array|object|null $threshold = null, array $extraContext = []): array
    {
        try {
            $resolvedChannel = $this->resolveChannel($channel);

            if ($resolvedChannel === null) {
                throw new \InvalidArgumentException("Unsupported alert channel [{$channel}].");
            }

            if ($this->channelConfig($channel) === null) {
                throw new \RuntimeException("Alert channel [{$channel}] is not configured.");
            }

            $threshold = $this->thresholdForTest($threshold);
            $resolvedChannel->send($threshold, array_merge($this->contextForTest($threshold), $extraContext));

            return [
                'channel' => $channel,
                'status' => 'sent',
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'channel' => $channel,
                'status' => 'failed',
                'error' => $this->safeError($e),
            ];
        }
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

    protected function channelConfig(string $name): mixed
    {
        $value = config("lookout.alerting.channels.{$name}");

        return empty($value) ? null : $value;
    }

    protected function safeError(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            return 'HTTP '.$e->response->status().' response from alert channel.';
        }

        return class_basename($e).' while testing alert channel.';
    }

    protected function thresholdForTest(array|object|null $threshold): object
    {
        $threshold = (object) ($threshold ?? []);

        $threshold->id ??= 0;
        $threshold->name ??= 'Lookout channel test';
        $threshold->metric ??= 'test';
        $threshold->condition ??= 'gte';
        $threshold->value ??= 1;
        $threshold->window_minutes ??= 15;
        $threshold->cooldown_minutes ??= 15;

        return $threshold;
    }

    /**
     * @return array<string, mixed>
     */
    protected function contextForTest(object $threshold): array
    {
        return [
            'test' => true,
            'test_message' => 'Lookout alert channel test',
            'threshold_id' => (int) $threshold->id,
            'name' => (string) $threshold->name,
            'metric' => (string) $threshold->metric,
            'condition' => (string) $threshold->condition,
            'value' => (float) $threshold->value,
            'threshold_value' => (float) $threshold->value,
            'window_minutes' => (int) $threshold->window_minutes,
            'cooldown_minutes' => (int) $threshold->cooldown_minutes,
        ];
    }
}
