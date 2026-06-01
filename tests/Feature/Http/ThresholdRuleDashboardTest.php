<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Zasetsu\Lookout\Alerting\AlertChannelTester;
use Zasetsu\Lookout\Alerting\ThresholdEvaluator;
use Zasetsu\Lookout\Alerting\ThresholdResult;
use Zasetsu\Lookout\Storage\StorageContract;
use Zasetsu\Lookout\Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->withoutMiddleware();

    config([
        'lookout.alerting.channels.email' => 'ops@example.test',
        'lookout.alerting.channels.slack' => null,
        'lookout.alerting.channels.webhook' => 'https://alerts.example.test/lookout',
    ]);
});

function thresholdRulePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'API latency',
        'metric' => 'request_duration_p95',
        'condition' => 'gte',
        'value' => '750',
        'window_minutes' => '15',
        'cooldown_minutes' => '30',
        'channels' => ['email', 'webhook'],
        'enabled' => '1',
    ], $overrides);
}

function createThresholdRule(array $overrides = []): array
{
    return app(StorageContract::class)->createThresholdRule(thresholdRulePayload($overrides));
}

function latestThresholdAudit(): array
{
    $entry = DB::connection(config('lookout.storage.connection', 'lookout'))
        ->table('lookout_audit_log')
        ->orderByDesc('id')
        ->first();

    expect($entry)->not->toBeNull();

    return [
        'action' => $entry->action,
        'user_id' => $entry->user_id,
        'ip' => $entry->ip,
        'details' => json_decode((string) $entry->details, true),
    ];
}

it('renders alerts with rules as the default tab and links to the create page', function () {
    /** @var TestCase $this */
    createThresholdRule();

    $this->get('/lookout/alerts')
        ->assertOk()
        ->assertSee('Rules')
        ->assertSee('Trigger History')
        ->assertSee('Delivery')
        ->assertSee('New rule')
        ->assertSee('API latency')
        ->assertSee('Evaluate now');
});

it('masks configured channel destinations on the delivery tab', function () {
    /** @var TestCase $this */
    config([
        'lookout.alerting.channels.slack' => 'https://hooks.slack.test/services/T000/B000/secret-token',
        'lookout.alerting.channels.webhook' => 'https://alerts.example.test/lookout?token=secret',
    ]);

    $this->get('/lookout/alerts?tab=delivery')
        ->assertOk()
        ->assertSee('op***@example.test')
        ->assertSee('https://hooks.slack.test/.../****')
        ->assertSee('https://alerts.example.test/.../****')
        ->assertDontSee('secret-token')
        ->assertDontSee('token=secret');
});

it('renders the create page with disabled unconfigured channels', function () {
    /** @var TestCase $this */
    $this->get('/lookout/alerts/rules/create')
        ->assertOk()
        ->assertSee('Create threshold rule')
        ->assertSee('Request p95 duration')
        ->assertSee('Email')
        ->assertSee('Slack')
        ->assertSee('Not configured');
});

it('creates threshold rules and audits the mutation', function () {
    /** @var TestCase $this */
    $this->post('/lookout/alerts/rules', thresholdRulePayload([
        'name' => 'Exceptions spike',
        'metric' => 'exception_count',
        'value' => '5',
        'channels' => ['email'],
    ]))
        ->assertRedirect('/lookout/alerts');

    $rule = app(StorageContract::class)->getThresholdRules()['data'][0];

    expect($rule['name'])->toBe('Exceptions spike')
        ->and($rule['metric'])->toBe('exception_count')
        ->and($rule['channels'])->toBe(['email'])
        ->and($rule['cooldown_minutes'])->toBe(30);

    $audit = latestThresholdAudit();

    expect($audit['action'])->toBe('threshold_rule_created')
        ->and($audit['details']['rule_id'])->toBe($rule['id'])
        ->and($audit['details']['name'])->toBe('Exceptions spike');
});

it('updates threshold rules and audits the mutation', function () {
    /** @var TestCase $this */
    $rule = createThresholdRule();

    $this->put("/lookout/alerts/rules/{$rule['id']}", thresholdRulePayload([
        'name' => 'Slow query volume',
        'metric' => 'slow_query_count',
        'condition' => 'gt',
        'value' => '10',
        'channels' => ['webhook'],
    ]))
        ->assertRedirect('/lookout/alerts');

    $updated = app(StorageContract::class)->getThresholdRule($rule['id']);

    expect($updated['name'])->toBe('Slow query volume')
        ->and($updated['metric'])->toBe('slow_query_count')
        ->and($updated['channels'])->toBe(['webhook']);

    $audit = latestThresholdAudit();

    expect($audit['action'])->toBe('threshold_rule_updated')
        ->and($audit['details']['rule_id'])->toBe($rule['id'])
        ->and($audit['details']['name'])->toBe('Slow query volume');
});

it('toggles threshold rules and audits the enabled state', function () {
    /** @var TestCase $this */
    $rule = createThresholdRule(['enabled' => '1']);

    $this->post("/lookout/alerts/rules/{$rule['id']}/toggle")
        ->assertRedirect('/lookout/alerts');

    $updated = app(StorageContract::class)->getThresholdRule($rule['id']);
    $audit = latestThresholdAudit();

    expect($updated['enabled'])->toBeFalse()
        ->and($audit['action'])->toBe('threshold_rule_disabled')
        ->and($audit['details']['rule_id'])->toBe($rule['id']);
});

it('deletes threshold rules and audits the mutation', function () {
    /** @var TestCase $this */
    $rule = createThresholdRule();

    $this->delete("/lookout/alerts/rules/{$rule['id']}")
        ->assertRedirect('/lookout/alerts');

    expect(app(StorageContract::class)->getThresholdRule($rule['id']))->toBeNull();

    $audit = latestThresholdAudit();

    expect($audit['action'])->toBe('threshold_rule_deleted')
        ->and($audit['details']['rule_id'])->toBe($rule['id']);
});

it('evaluates threshold rules and audits the dry run result', function () {
    /** @var TestCase $this */
    $rule = createThresholdRule();

    app()->instance(ThresholdEvaluator::class, new class(app(StorageContract::class)) extends ThresholdEvaluator
    {
        public function dryRun(array|object $threshold): ThresholdResult
        {
            return new ThresholdResult(
                thresholdId: (int) $threshold['id'],
                name: (string) $threshold['name'],
                metric: (string) $threshold['metric'],
                condition: (string) $threshold['condition'],
                thresholdValue: (float) $threshold['value'],
                currentValue: 900.0,
                windowMinutes: (int) $threshold['window_minutes'],
                cooldownMinutes: (int) $threshold['cooldown_minutes'],
                triggered: true,
            );
        }
    });

    $this->post("/lookout/alerts/rules/{$rule['id']}/evaluate")
        ->assertRedirect('/lookout/alerts');

    $audit = latestThresholdAudit();

    expect($audit['action'])->toBe('threshold_rule_evaluated')
        ->and($audit['details']['rule_id'])->toBe($rule['id'])
        ->and($audit['details']['result']['current_value'])->toBe(900)
        ->and($audit['details']['result']['triggered'])->toBeTrue();
});

it('dispatches threshold rules and audits the manual dispatch result', function () {
    /** @var TestCase $this */
    $rule = createThresholdRule();

    app()->instance(ThresholdEvaluator::class, new class(app(StorageContract::class)) extends ThresholdEvaluator
    {
        public function dispatchManually(array|object $threshold): array
        {
            return [
                'dispatched' => true,
                'reason' => 'sent',
                'result' => ['threshold_id' => (int) $threshold['id'], 'triggered' => true],
                'deliveries' => [['channel' => 'email', 'status' => 'sent']],
            ];
        }
    });

    $this->post("/lookout/alerts/rules/{$rule['id']}/dispatch")
        ->assertRedirect('/lookout/alerts');

    $audit = latestThresholdAudit();

    expect($audit['action'])->toBe('threshold_rule_dispatched')
        ->and($audit['details']['rule_id'])->toBe($rule['id'])
        ->and($audit['details']['result']['reason'])->toBe('sent');
});

it('tests configured alert channels and audits the result', function () {
    /** @var TestCase $this */
    app()->instance(AlertChannelTester::class, new class extends AlertChannelTester
    {
        public function test(string $channel, array|object|null $threshold = null, array $extraContext = []): array
        {
            return ['channel' => $channel, 'status' => 'sent', 'error' => null];
        }
    });

    $this->post('/lookout/alerts/channels/email/test')
        ->assertRedirect('/lookout/alerts');

    $audit = latestThresholdAudit();

    expect($audit['action'])->toBe('threshold_channel_tested')
        ->and($audit['details'])->toBe(['channel' => 'email', 'result' => ['channel' => 'email', 'status' => 'sent', 'error' => null]]);
});

it('rejects invalid threshold rule input', function () {
    /** @var TestCase $this */
    $this->from('/lookout/alerts/rules/create')
        ->post('/lookout/alerts/rules', thresholdRulePayload([
            'metric' => 'unknown_metric',
            'condition' => 'between',
            'value' => 'not numeric',
            'window_minutes' => '0',
            'cooldown_minutes' => '20000',
            'channels' => ['sms'],
        ]))
        ->assertRedirect('/lookout/alerts/rules/create')
        ->assertSessionHasErrors(['metric', 'condition', 'value', 'window_minutes', 'cooldown_minutes', 'channels.0']);
});

it('rejects valid channel names that are not configured', function () {
    /** @var TestCase $this */
    $this->from('/lookout/alerts/rules/create')
        ->post('/lookout/alerts/rules', thresholdRulePayload([
            'channels' => ['slack'],
        ]))
        ->assertRedirect('/lookout/alerts/rules/create')
        ->assertSessionHasErrors(['channels.0']);
});

it('audits sanitized channel test errors', function () {
    /** @var TestCase $this */
    Http::fake([
        'alerts.example.test/*' => Http::response('secret response body', 500),
    ]);

    $this->post('/lookout/alerts/channels/webhook/test')
        ->assertRedirect('/lookout/alerts');

    $audit = latestThresholdAudit();

    expect($audit['action'])->toBe('threshold_channel_tested')
        ->and($audit['details']['result']['error'])->toBe('HTTP 500 response from alert channel.');
    expect($audit['details']['result']['error'])->not->toContain('secret response body');
});

it('returns not found for missing and non-numeric rule ids', function () {
    /** @var TestCase $this */
    $this->get('/lookout/alerts/rules/999/edit')->assertNotFound();
    $this->get('/lookout/alerts/rules/not-a-number/edit')->assertNotFound();
});
