<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Zasetsu\Lookout\Alerting\Channels\SlackChannel;
use Zasetsu\Lookout\Alerting\Channels\WebhookChannel;

function alertThresholdStub(): object
{
    return (object) [
        'id' => 1,
        'name' => 'High exceptions',
        'metric' => 'exception_count',
        'condition' => 'gte',
        'value' => 5,
        'window_minutes' => 15,
    ];
}

describe('Alert channels', function () {
    it('throws on failed slack webhook responses', function () {
        config(['lookout.alerting.channels.slack' => 'https://hooks.slack.test/services/test']);
        Http::fake([
            'hooks.slack.test/*' => Http::response('server error', 500),
        ]);

        expect(fn () => app(SlackChannel::class)->send(alertThresholdStub(), []))
            ->toThrow(RequestException::class);
    });

    it('throws on failed generic webhook responses', function () {
        config(['lookout.alerting.channels.webhook' => 'https://alerts.example.test/lookout']);
        Http::fake([
            'alerts.example.test/*' => Http::response('server error', 500),
        ]);

        expect(fn () => app(WebhookChannel::class)->send(alertThresholdStub(), []))
            ->toThrow(RequestException::class);
    });
});
