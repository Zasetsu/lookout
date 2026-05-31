@extends('lookout::layouts.app')
@section('title', 'Request Detail')
@php
    $pageConfig = [
        'id' => 'requests',
        'title' => 'Request Detail',
        'crumbs' => [
            ['label' => 'Requests', 'href' => route('lookout.requests')],
            ['label' => $trace['trace_id'] ?? 'Trace'],
        ],
    ];
@endphp
@section('content')
@php
    $code = (int) ($trace['response_status'] ?? 0);
    $duration = (int) ($trace['duration'] ?? 0);
    $memoryMb = isset($trace['memory_peak']) ? round($trace['memory_peak'] / 1024 / 1024, 1) : null;
    $eventCount = count($events);
    $queries = collect($events)->where('event_type', 'query')->count();
    $exceptions = collect($events)->where('event_type', 'exception')->count();
    $httpEvents = collect($events)->where('event_type', 'outgoing_http')->count();
    $timelineMax = max($duration, collect($events)->max('duration') ?? 1, 1);
    $eventClass = function (string $type): string {
        return match (true) {
            str_contains($type, 'query') => 'q',
            str_contains($type, 'cache') => 'cache',
            str_contains($type, 'http') => 'http',
            str_contains($type, 'exception') => 'exc',
            str_contains($type, 'mail') => 'mail',
            str_contains($type, 'job') => 'job',
            default => '',
        };
    };
@endphp

<div class="page-title-row" style="margin-bottom:14px">
    <span class="pt">{{ $trace['method'] ?? 'GET' }} {{ $trace['name'] ?? 'unknown' }}</span>
    <span class="psub mono">{{ $trace['trace_id'] ?? '' }}</span>
    <div class="right">
        <a class="btn sm" href="{{ route('lookout.requests') }}">Back</a>
    </div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(6,1fr)">
    <div class="kpi"><span class="k-lbl">Status</span><span class="k-val {{ $code >= 500 ? 's-err' : ($code >= 400 ? 's-warn' : 's-ok') }}">{{ $code ?: 'n/a' }}</span><span class="k-sub">{{ $trace['status'] ?? 'unknown' }}</span></div>
    <div class="kpi"><span class="k-lbl">Duration</span><span class="k-val {{ $duration > 1000 ? 's-err' : ($duration > 500 ? 's-warn' : '') }}">{{ number_format($duration) }}<span class="u">ms</span></span><span class="k-sub">full request lifecycle</span></div>
    <div class="kpi"><span class="k-lbl">Events</span><span class="k-val">{{ number_format($eventCount) }}</span><span class="k-sub">captured child events</span></div>
    <div class="kpi"><span class="k-lbl">Queries</span><span class="k-val">{{ number_format($queries) }}</span><span class="k-sub">database events</span></div>
    <div class="kpi"><span class="k-lbl">Exceptions</span><span class="k-val {{ $exceptions > 0 ? 's-err' : '' }}">{{ number_format($exceptions) }}</span><span class="k-sub">exception events</span></div>
    <div class="kpi"><span class="k-lbl">Memory peak</span><span class="k-val">{{ $memoryMb !== null ? $memoryMb : 'n/a' }}<span class="u">{{ $memoryMb !== null ? 'MB' : '' }}</span></span><span class="k-sub">{{ $trace['environment'] ?? app()->environment() }}</span></div>
</div>

<div class="grid split mb12" style="grid-template-columns:1fr 1fr">
    <div class="panel">
        <div class="panel-h"><h3>Request metadata</h3></div>
        <div class="panel-b">
            <dl class="def-list">
                <dt>Method</dt><dd>{{ $trace['method'] ?? 'n/a' }}</dd>
                <dt>Route</dt><dd class="wrap-anywhere">{{ $trace['name'] ?? 'n/a' }}</dd>
                <dt>URL</dt><dd class="wrap-anywhere">{{ $trace['url'] ?? 'n/a' }}</dd>
                <dt>User</dt><dd>{{ $trace['user_id'] ?? 'anonymous' }}</dd>
                <dt>IP</dt><dd>{{ $trace['ip'] ?? 'n/a' }}</dd>
                <dt>Timestamp</dt><dd>{{ $trace['timestamp'] ?? 'n/a' }}</dd>
            </dl>
        </div>
    </div>
    <div class="panel">
        <div class="panel-h"><h3>HTTP payload</h3></div>
        <div class="panel-b">
            <dl class="def-list">
                <dt>Request headers</dt><dd>{{ $trace['request_headers'] ? 'captured' : 'empty' }}</dd>
                <dt>Request body</dt><dd>{{ $trace['request_body'] ? 'captured' : 'empty' }}</dd>
                <dt>Response status</dt><dd>{{ $code ?: 'n/a' }}</dd>
                <dt>Response headers</dt><dd>{{ $trace['response_headers'] ? 'captured' : 'empty' }}</dd>
                <dt>Outgoing HTTP</dt><dd>{{ number_format($httpEvents) }} events</dd>
            </dl>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-h"><h3>Trace timeline</h3><span class="sub">{{ number_format($eventCount) }} events</span></div>
    <div class="panel-b">
        <div class="trace">
            @forelse($events as $event)
                @php
                    $eventType = (string) ($event['event_type'] ?? 'event');
                    $eventDuration = (int) ($event['duration'] ?? 0);
                    $width = max(2, min(100, round(($eventDuration ?: 2) / $timelineMax * 100, 1)));
                    $payload = $event['payload'] ?? '';
                    $prettyPayload = $payload;
                    $decoded = is_string($payload) ? json_decode($payload, true) : null;
                    if (is_array($decoded)) {
                        $prettyPayload = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                @endphp
                <details class="tl-event">
                    <summary class="tl-meta">
                        <span class="badge neu">{{ str_replace('_', ' ', $eventType) }}</span>
                        <span class="t-time">{{ $event['timestamp'] ?? '' }}</span>
                    </summary>
                    <div class="tl-track">
                        <div class="tl-bar {{ $eventClass($eventType) }}" style="width:{{ $width }}%">
                            <span class="lab">{{ $eventDuration > 0 ? number_format($eventDuration).'ms' : 'instant' }} · {{ $event['labels'] ?? $eventType }}</span>
                        </div>
                    </div>
                    <div class="detail-inner" style="display:block;grid-column:1 / -1;padding:0 0 14px 150px">
                        <pre class="code">{{ $prettyPayload }}</pre>
                    </div>
                </details>
            @empty
                <div class="empty"><h4>No child events</h4><p>This trace does not contain query, cache, log, HTTP, mail, notification, job, or exception events.</p></div>
            @endforelse
        </div>
    </div>
</div>
@endsection
