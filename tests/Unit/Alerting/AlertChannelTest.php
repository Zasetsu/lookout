<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Zasetsu\Lookout\Alerting\AlertChannelTester;
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
    it('tests a global slack channel with visible test context', function () {
        config(['lookout.alerting.channels.slack' => 'https://hooks.slack.test/services/test']);
        Http::fake([
            'hooks.slack.test/*' => Http::response('ok'),
        ]);

        $result = app(AlertChannelTester::class)->test('slack', null, [
            'initiated_by' => 'Dashboard user',
        ]);

        expect($result)->toBe([
            'channel' => 'slack',
            'status' => 'sent',
            'error' => null,
        ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://hooks.slack.test/services/test'
                && str_contains((string) $payload['text'], '[Lookout Alert Test]')
                && str_contains((string) $payload['blocks'][0]['text']['text'], 'Dashboard user');
        });
    });

    it('tests a rule-specific webhook channel with threshold and test context', function () {
        config(['lookout.alerting.channels.webhook' => 'https://alerts.example.test/lookout']);
        Http::fake([
            'alerts.example.test/*' => Http::response(['ok' => true]),
        ]);

        $result = app(AlertChannelTester::class)->test('webhook', (object) [
            'id' => 9,
            'name' => 'High error rate',
            'metric' => 'error_rate',
            'condition' => 'gte',
            'value' => 5,
            'window_minutes' => 10,
            'channels' => ['webhook'],
        ], [
            'initiated_by' => 'Dashboard user',
        ]);

        expect($result['channel'])->toBe('webhook')
            ->and($result['status'])->toBe('sent')
            ->and($result['error'])->toBeNull();

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://alerts.example.test/lookout'
                && $payload['test'] === true
                && $payload['threshold_id'] === 9
                && $payload['initiated_by'] === 'Dashboard user';
        });
    });

    it('returns failed channel tester results without throwing', function () {
        config(['lookout.alerting.channels.webhook' => 'https://alerts.example.test/lookout']);
        Http::fake([
            'alerts.example.test/*' => Http::response('server error', 500),
        ]);

        $result = app(AlertChannelTester::class)->test('webhook');

        expect($result['channel'])->toBe('webhook')
            ->and($result['status'])->toBe('failed')
            ->and($result['error'])->toBe('HTTP 500 response from alert channel.');
    });

    it('does not expose raw channel test response bodies in failed results', function () {
        config(['lookout.alerting.channels.webhook' => 'https://alerts.example.test/lookout']);
        Http::fake([
            'alerts.example.test/*' => Http::response('secret response body', 500),
        ]);

        $result = app(AlertChannelTester::class)->test('webhook');

        expect($result['error'])->toBe('HTTP 500 response from alert channel.');
        expect($result['error'])->not->toContain('secret response body');
    });

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
