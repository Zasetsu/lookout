<?php

namespace Zasetsu\Lookout\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Zasetsu\Lookout\Alerting\AlertChannelTester;
use Zasetsu\Lookout\Alerting\ThresholdEvaluator;
use Zasetsu\Lookout\Http\Concerns\NormalizesFilterInput;
use Zasetsu\Lookout\Http\Middleware\Authorize;
use Zasetsu\Lookout\Http\Middleware\BasicAuth;
use Zasetsu\Lookout\Http\Middleware\IpWhitelist;
use Zasetsu\Lookout\Http\Middleware\LocalhostOnly;
use Zasetsu\Lookout\Http\Support\Payload;
use Zasetsu\Lookout\Storage\StorageContract;

class DashboardController extends Controller
{
    use NormalizesFilterInput;

    private const THRESHOLD_METRICS = [
        'request_duration',
        'request_duration_p95',
        'exception_count',
        'slow_query_count',
        'failed_job_count',
        'error_rate',
        'outgoing_http_failure_count',
    ];

    private const THRESHOLD_CONDITIONS = ['gt', 'gte', 'lt', 'lte', 'eq'];

    private const ALERT_CHANNELS = ['email', 'slack', 'webhook'];

    public function __construct(
        private StorageContract $storage,
    ) {
        $this->middleware('throttle:'.config('lookout.dashboard.rate_limit', 60));

        $this->middleware([
            Authorize::class,
            IpWhitelist::class,
            BasicAuth::class,
            LocalhostOnly::class,
        ]);
    }

    public static function navItems(): array
    {
        return [
            ['route' => 'lookout.overview', 'label' => 'Overview', 'icon' => '📊'],
            ['route' => 'lookout.requests', 'label' => 'Requests', 'icon' => '🌐'],
            ['route' => 'lookout.exceptions', 'label' => 'Exceptions', 'icon' => '🔴'],
            ['route' => 'lookout.queries', 'label' => 'Queries', 'icon' => '🗃️'],
            ['route' => 'lookout.jobs', 'label' => 'Jobs', 'icon' => '⚙️'],
            ['route' => 'lookout.scheduled', 'label' => 'Scheduled', 'icon' => '⏰'],
            ['route' => 'lookout.commands', 'label' => 'Commands', 'icon' => '💻'],
            ['route' => 'lookout.cache', 'label' => 'Cache', 'icon' => '💾'],
            ['route' => 'lookout.mail', 'label' => 'Mail', 'icon' => '📧'],
            ['route' => 'lookout.notifications', 'label' => 'Notifications', 'icon' => '🔔'],
            ['route' => 'lookout.logs', 'label' => 'Logs', 'icon' => '📋'],
            ['route' => 'lookout.outgoing', 'label' => 'Outgoing HTTP', 'icon' => '📤'],
            ['route' => 'lookout.alerts', 'label' => 'Alerts', 'icon' => '🚨'],
            ['route' => 'lookout.audit', 'label' => 'Audit', 'icon' => '🧾'],
            ['route' => 'lookout.health', 'label' => 'Health', 'icon' => '🩺'],
        ];
    }

    public function overview()
    {
        $summary = $this->storage->getSummary('-24 hours');
        $volume = $this->storage->getRequestVolumeByHour('-24 hours');
        $statusDist = $this->storage->getStatusDistribution('-24 hours');
        $topExceptions = $this->storage->getTopExceptions(5);

        return view('lookout::overview', [
            'title' => 'Overview',
            'summary' => $summary,
            'volume' => $volume,
            'statusDist' => $statusDist,
            'topExceptions' => $topExceptions,
        ]);
    }

    public function requests(Request $request)
    {
        $filters = $this->allowedScalarFilters($request, [
            'status' => 'status',
            'name' => 'route',
            'method' => 'method',
            'since' => 'since',
        ], [
            'status' => ['success', 'error'],
            'method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
        ]);

        if ($filters === false) {
            abort(422, 'Invalid filter parameter.');
        }

        $since = $this->normalizeSinceFilter($filters['since'] ?? null);
        if ($since === false) {
            abort(422, 'Invalid since parameter.');
        }

        if ($since !== null) {
            $filters['since'] = $since;
        }

        $minDuration = $this->optionalIntegerFilter($request, 'min_duration', 1);
        if ($minDuration === false) {
            abort(422, 'Invalid min_duration parameter.');
        }

        if ($minDuration !== null) {
            $filters['min_duration'] = $minDuration;
        }

        $responseStatus = $this->optionalIntegerFilter($request, 'response_status', 100, 599, false);
        if ($responseStatus === false) {
            abort(422, 'Invalid response_status parameter.');
        }

        if ($responseStatus !== null) {
            $filters['response_status'] = $responseStatus;
        }

        $filters = array_filter(array_merge([
            'type' => 'request',
            'since' => $filters['since'] ?? now()->subHours(24)->toDateTimeString(),
        ], $filters), fn ($v) => $v !== null && $v !== '');

        $page = $this->integerParameter($request, 'page', 1, 1);
        if ($page === false) {
            abort(422, 'Invalid page parameter.');
        }

        $result = $this->storage->getTraces($filters, 25, ($page - 1) * 25);
        $volume = $this->storage->getRequestVolumeByHour('-24 hours');
        $statusDist = $this->storage->getStatusDistribution('-24 hours');
        $summary = $this->storage->getSummary('-24 hours');

        return view('lookout::requests.index', [
            'title' => 'Requests',
            'traces' => $result['data'],
            'total' => $result['total'],
            'volume' => $volume,
            'statusDist' => $statusDist,
            'summary' => $summary,
        ]);
    }

    public function requestDetail(string $traceId)
    {
        $trace = $this->storage->getTrace($traceId);
        $events = $this->storage->getEvents($traceId);

        return view('lookout::requests.detail', [
            'title' => 'Request Detail',
            'trace' => $trace,
            'events' => $events,
        ]);
    }

    public function exceptions(Request $request)
    {
        $filters = $this->allowedScalarFilters($request, [
            'status' => 'status',
            'class' => 'class',
        ], [
            'status' => ['unresolved', 'resolved', 'ignored'],
        ]);

        if ($filters === false) {
            abort(422, 'Invalid filter parameter.');
        }

        $filters = array_filter(array_merge([
            'status' => $filters['status'] ?? 'unresolved',
        ], $filters), fn ($v) => $v !== null && $v !== '');

        $page = $this->integerParameter($request, 'page', 1, 1);
        if ($page === false) {
            abort(422, 'Invalid page parameter.');
        }

        $result = $this->storage->getExceptionGroups($filters, 25, ($page - 1) * 25);
        $trend = $this->storage->getEventsByHour('exception', '-24 hours');

        return view('lookout::exceptions.index', [
            'title' => 'Exceptions',
            'groups' => $result['data'],
            'total' => $result['total'],
            'trend' => $trend,
            'statusCounts' => $this->storage->getExceptionGroupStatusCounts(),
        ]);
    }

    public function exceptionDetail(int $groupId)
    {
        $group = $this->storage->getExceptionGroup($groupId);

        return view('lookout::exceptions.detail', [
            'title' => 'Exception Detail',
            'group' => $group,
        ]);
    }

    public function resolveException(int $groupId)
    {
        if ($this->storage->resolveExceptionGroup($groupId)) {
            $this->auditExceptionGroupMutation('exception_group_resolved', $groupId);
        }

        return redirect()->route('lookout.exceptions')->with('success', 'Exception resolved.');
    }

    public function ignoreException(int $groupId)
    {
        if ($this->storage->ignoreExceptionGroup($groupId)) {
            $this->auditExceptionGroupMutation('exception_group_ignored', $groupId);
        }

        return redirect()->route('lookout.exceptions')->with('success', 'Exception ignored.');
    }

    protected function auditExceptionGroupMutation(string $action, int $groupId): void
    {
        $request = request();
        $userId = $request->user()?->getAuthIdentifier();

        $this->storage->logAudit(
            $action,
            $userId !== null ? (string) $userId : null,
            $request->ip(),
            ['group_id' => $groupId],
        );
    }

    public function queries(Request $request)
    {
        $threshold = $this->integerParameter($request, 'threshold', 100, 1);

        if ($threshold === false) {
            abort(422, 'Invalid threshold parameter.');
        }

        $queries = $this->storage->getSlowQueries($threshold, 50);
        $buckets = $this->storage->getQueryDurationBuckets(50);
        $trend = $this->storage->getEventsByHour('query', '-24 hours');

        return view('lookout::queries.index', [
            'title' => 'Slow Queries',
            'queries' => $queries,
            'threshold' => $threshold,
            'buckets' => $buckets,
            'trend' => $trend,
        ]);
    }

    public function jobs()
    {
        $jobEvents = $this->storage->getEventsByType('job_processed', [], 50);
        $failedEvents = $this->storage->getEventsByType('job_failed', [], 50);
        $allJobs = collect(array_merge($jobEvents['data'], $failedEvents['data']))
            ->sortByDesc('timestamp')
            ->values()
            ->all();

        $processedTotal = $this->storage->getTotalEventsCount('job_processed');
        $failedTotal = $this->storage->getTotalEventsCount('job_failed');

        return view('lookout::jobs.index', [
            'title' => 'Jobs',
            'jobs' => $allJobs,
            'total_processed' => $jobEvents['total'],
            'total_failed' => $failedEvents['total'],
            'processed_all' => $processedTotal,
            'failed_all' => $failedTotal,
        ]);
    }

    public function scheduled()
    {
        $result = $this->storage->getTraces(['type' => 'scheduled_task'], 50, 0);

        return view('lookout::scheduled.index', [
            'title' => 'Scheduled Tasks',
            'tasks' => $result['data'],
            'total' => $result['total'],
        ]);
    }

    public function commands()
    {
        $result = $this->storage->getTraces(['type' => 'command'], 25, 0);

        return view('lookout::commands.index', ['title' => 'Commands', 'commands' => $result['data'], 'total' => $result['total']]);
    }

    public function cache()
    {
        $stats = $this->storage->getCacheStats('-24 hours');
        $recentEvents = $this->storage->getEventsByType('cache', [], 50);
        $trend = $this->storage->getEventsByHour('cache', '-24 hours');

        return view('lookout::cache.index', [
            'title' => 'Cache',
            'stats' => $stats,
            'events' => $recentEvents['data'],
            'trend' => $trend,
        ]);
    }

    public function mail()
    {
        $result = $this->storage->getEventsByType('mail', [], 50);

        $uniqueSubjects = collect($result['data'])
            ->map(function ($m) {
                $subject = Payload::string(Payload::decode($m['payload'] ?? null), 'subject');

                return $subject !== '' ? $subject : null;
            })
            ->filter()
            ->unique()
            ->count();

        $uniqueRecipients = collect($result['data'])
            ->flatMap(fn ($m) => Payload::stringList(Payload::decode($m['payload'] ?? null), 'to'))
            ->unique()
            ->count();

        return view('lookout::mail.index', [
            'title' => 'Mail',
            'mails' => $result['data'],
            'total' => $result['total'],
            'uniqueSubjects' => $uniqueSubjects,
            'uniqueRecipients' => $uniqueRecipients,
        ]);
    }

    public function notifications()
    {
        $result = $this->storage->getEventsByType('notification', [], 50);

        $uniqueTypes = collect($result['data'])
            ->map(function ($n) {
                $notification = Payload::string(Payload::decode($n['payload'] ?? null), 'notification');

                return $notification !== '' ? class_basename($notification) : null;
            })
            ->filter()
            ->unique()
            ->count();

        $uniqueChannels = collect($result['data'])
            ->map(function ($n) {
                $channel = Payload::string(Payload::decode($n['payload'] ?? null), 'channel');

                return $channel !== '' ? $channel : null;
            })
            ->filter()
            ->unique()
            ->count();

        return view('lookout::notifications.index', [
            'title' => 'Notifications',
            'notifications' => $result['data'],
            'total' => $result['total'],
            'uniqueTypes' => $uniqueTypes,
            'uniqueChannels' => $uniqueChannels,
        ]);
    }

    public function logs()
    {
        $result = $this->storage->getEventsByType('log', [], 50);

        return view('lookout::logs.index', [
            'title' => 'Logs',
            'logs' => $result['data'],
            'total' => $result['total'],
        ]);
    }

    public function outgoing()
    {
        $result = $this->storage->getEventsByType('outgoing_http', [], 50);

        return view('lookout::outgoing.index', [
            'title' => 'Outgoing HTTP',
            'requests' => $result['data'],
            'total' => $result['total'],
        ]);
    }

    public function alerts(Request $request)
    {
        $tab = $request->query('tab', 'rules');
        $activeTab = is_string($tab) && in_array($tab, ['rules', 'history', 'delivery'], true) ? $tab : 'rules';
        $history = $this->storage->getAuditLog(['action' => 'threshold_triggered'], 50, 0);
        $rules = $this->storage->getThresholdRules([], 50, 0);

        return view('lookout::alerts.index', [
            'title' => 'Alerts',
            'activeTab' => $activeTab,
            'entries' => $history['data'],
            'total' => $history['total'],
            'rules' => $rules['data'],
            'rulesTotal' => $rules['total'],
            'metrics' => $this->thresholdMetricLabels(),
            'conditions' => $this->thresholdConditionLabels(),
            'channels' => $this->alertChannelState(),
        ]);
    }

    public function createThresholdRule()
    {
        return view('lookout::alerts.rules.create', [
            'title' => 'Create threshold rule',
            'rule' => [
                'name' => '',
                'metric' => 'request_duration_p95',
                'condition' => 'gte',
                'value' => '',
                'window_minutes' => 15,
                'cooldown_minutes' => 15,
                'channels' => [],
                'enabled' => true,
            ],
            'metrics' => $this->thresholdMetricLabels(),
            'conditions' => $this->thresholdConditionLabels(),
            'channels' => $this->alertChannelState(),
        ]);
    }

    public function storeThresholdRule(Request $request)
    {
        $rule = $this->storage->createThresholdRule($this->thresholdRuleInput($request));

        $this->auditThresholdRule('threshold_rule_created', $rule);

        return redirect()->route('lookout.alerts')->with('success', 'Threshold rule created.');
    }

    public function editThresholdRule(int $ruleId)
    {
        $rule = $this->findThresholdRule($ruleId);

        return view('lookout::alerts.rules.edit', [
            'title' => 'Edit threshold rule',
            'rule' => $rule,
            'metrics' => $this->thresholdMetricLabels(),
            'conditions' => $this->thresholdConditionLabels(),
            'channels' => $this->alertChannelState(),
        ]);
    }

    public function updateThresholdRule(Request $request, int $ruleId)
    {
        $this->findThresholdRule($ruleId);

        $rule = $this->storage->updateThresholdRule($ruleId, $this->thresholdRuleInput($request));

        $this->auditThresholdRule('threshold_rule_updated', $rule);

        return redirect()->route('lookout.alerts')->with('success', 'Threshold rule updated.');
    }

    public function toggleThresholdRule(int $ruleId)
    {
        $rule = $this->findThresholdRule($ruleId);
        $enabled = ! (bool) ($rule['enabled'] ?? false);
        $updated = $this->storage->setThresholdRuleEnabled($ruleId, $enabled);

        $this->auditThresholdRule($enabled ? 'threshold_rule_enabled' : 'threshold_rule_disabled', $updated);

        return redirect()->route('lookout.alerts')->with('success', $enabled ? 'Threshold rule enabled.' : 'Threshold rule disabled.');
    }

    public function deleteThresholdRule(int $ruleId)
    {
        $rule = $this->findThresholdRule($ruleId);

        $this->storage->deleteThresholdRule($ruleId);
        $this->auditThresholdRule('threshold_rule_deleted', $rule);

        return redirect()->route('lookout.alerts')->with('success', 'Threshold rule deleted.');
    }

    public function evaluateThresholdRule(int $ruleId)
    {
        $rule = $this->findThresholdRule($ruleId);
        $result = app(ThresholdEvaluator::class)->dryRun($rule)->toArray();

        $this->auditThresholdRule('threshold_rule_evaluated', $rule, ['result' => $result]);

        return redirect()->route('lookout.alerts')->with('success', $result['triggered'] ? 'Threshold condition is currently met.' : 'Threshold condition is not currently met.');
    }

    public function dispatchThresholdRule(int $ruleId)
    {
        $rule = $this->findThresholdRule($ruleId);
        $result = app(ThresholdEvaluator::class)->dispatchManually($rule);

        $this->auditThresholdRule('threshold_rule_dispatched', $rule, ['result' => $result]);

        return redirect()->route('lookout.alerts')->with('success', 'Manual dispatch finished: '.$result['reason'].'.');
    }

    public function testAlertChannel(string $channel)
    {
        if (! in_array($channel, self::ALERT_CHANNELS, true)) {
            abort(404);
        }

        $result = app(AlertChannelTester::class)->test($channel);

        $this->auditDashboardMutation('threshold_channel_tested', [
            'channel' => $channel,
            'result' => $result,
        ]);

        return redirect()->route('lookout.alerts')->with('success', 'Channel test finished: '.$result['status'].'.');
    }

    public function audit(Request $request)
    {
        $filters = $this->scalarFilters($request, [
            'action' => 'action',
            'since' => 'since',
        ]);

        if ($filters === false) {
            abort(422, 'Invalid audit filter parameter.');
        }

        $since = $this->normalizeSinceFilter($filters['since'] ?? null);
        if ($since === false) {
            abort(422, 'Invalid since parameter.');
        }

        if ($since !== null) {
            $filters['since'] = $since;
        }

        $page = $this->integerParameter($request, 'page', 1, 1);
        if ($page === false) {
            abort(422, 'Invalid page parameter.');
        }

        $result = $this->storage->getAuditLog($filters, 50, ($page - 1) * 50);

        return view('lookout::audit.index', [
            'title' => 'Audit Log',
            'entries' => $result['data'],
            'total' => $result['total'],
            'filters' => $filters,
        ]);
    }

    public function exportAudit(Request $request)
    {
        $format = $this->scalarFilterValue($request, 'format') ?? 'csv';

        if ($format === false || ! in_array((string) $format, ['csv', 'json'], true)) {
            abort(422, 'Invalid audit export format.');
        }

        $result = $this->storage->getAuditLog([], 1000, 0);

        if ($format === 'json') {
            return response()->json([
                'data' => $result['data'],
                'meta' => [
                    'total' => $result['total'],
                    'limit' => 1000,
                ],
            ]);
        }

        return response($this->auditCsv($result['data']), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="lookout-audit-log.csv"',
        ]);
    }

    public function health()
    {
        return view('lookout::health.index', [
            'title' => 'Health',
            'health' => $this->storage->getHealth(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    protected function auditCsv(array $entries): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['created_at', 'action', 'user_id', 'ip', 'details']);

        foreach ($entries as $entry) {
            $details = $entry['details'] ?? null;
            if (is_array($details)) {
                $details = json_encode($details);
            }

            fputcsv($handle, [
                $entry['created_at'] ?? '',
                $entry['action'] ?? '',
                $entry['user_id'] ?? '',
                $entry['ip'] ?? '',
                is_string($details) ? $details : '',
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return is_string($contents) ? $contents : '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function thresholdRuleInput(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'metric' => ['required', 'string', Rule::in(self::THRESHOLD_METRICS)],
            'condition' => ['required', 'string', Rule::in(self::THRESHOLD_CONDITIONS)],
            'value' => ['required', 'numeric'],
            'window_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'channels' => ['array'],
            'channels.*' => ['string', Rule::in($this->configuredAlertChannels())],
            'enabled' => ['boolean'],
        ]);

        return [
            'name' => $validated['name'],
            'metric' => $validated['metric'],
            'condition' => $validated['condition'],
            'value' => (float) $validated['value'],
            'window_minutes' => (int) $validated['window_minutes'],
            'cooldown_minutes' => (int) $validated['cooldown_minutes'],
            'channels' => array_values($validated['channels'] ?? []),
            'enabled' => $request->boolean('enabled'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function thresholdMetricLabels(): array
    {
        return [
            'request_duration' => 'Average request duration',
            'request_duration_p95' => 'Request p95 duration',
            'exception_count' => 'Exception count',
            'slow_query_count' => 'Slow query count',
            'failed_job_count' => 'Failed job count',
            'error_rate' => 'Error rate',
            'outgoing_http_failure_count' => 'Outgoing HTTP failures',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function thresholdConditionLabels(): array
    {
        return [
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'eq' => '=',
        ];
    }

    /**
     * @return array<string, array{label: string, configured: bool, destination: string|null}>
     */
    protected function alertChannelState(): array
    {
        $labels = [
            'email' => 'Email',
            'slack' => 'Slack',
            'webhook' => 'Webhook',
        ];

        $channels = [];

        foreach ($labels as $name => $label) {
            $destination = config("lookout.alerting.channels.{$name}");
            $channels[$name] = [
                'label' => $label,
                'configured' => ! empty($destination),
                'destination' => is_string($destination) ? $this->maskedAlertDestination($destination) : null,
            ];
        }

        return $channels;
    }

    /**
     * @return array<int, string>
     */
    protected function configuredAlertChannels(): array
    {
        return array_keys(array_filter(
            $this->alertChannelState(),
            fn (array $channel): bool => $channel['configured'],
        ));
    }

    protected function maskedAlertDestination(string $destination): string
    {
        if (filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            [$local, $domain] = explode('@', $destination, 2);
            $prefix = substr($local, 0, 2);

            return $prefix.'***@'.$domain;
        }

        $parts = parse_url($destination);

        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            return $parts['scheme'].'://'.$parts['host'].'/.../****';
        }

        return 'configured';
    }

    /**
     * @return array<string, mixed>
     */
    protected function findThresholdRule(int $ruleId): array
    {
        $rule = $this->storage->getThresholdRule($ruleId);

        if ($rule === null) {
            abort(404);
        }

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $extra
     */
    protected function auditThresholdRule(string $action, array $rule, array $extra = []): void
    {
        $this->auditDashboardMutation($action, array_merge([
            'rule_id' => (int) ($rule['id'] ?? 0),
            'name' => (string) ($rule['name'] ?? ''),
            'metric' => (string) ($rule['metric'] ?? ''),
            'enabled' => (bool) ($rule['enabled'] ?? false),
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $details
     */
    protected function auditDashboardMutation(string $action, array $details): void
    {
        $request = request();
        $userId = $request->user()?->getAuthIdentifier();

        $this->storage->logAudit(
            $action,
            $userId !== null ? (string) $userId : null,
            $request->ip(),
            $details,
        );
    }
}
