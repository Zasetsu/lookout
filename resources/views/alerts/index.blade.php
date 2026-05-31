@extends('lookout::layouts.app')
@section('title', 'Alerts')
@section('content')
@php
    use Zasetsu\Lookout\Http\Support\Payload;
    $decodeDetails = fn ($details) => is_array($details) ? $details : Payload::decode($details);
    $deliveryStats = collect($entries)->flatMap(fn ($entry) => $decodeDetails($entry['details'] ?? null)['deliveries'] ?? []);
    $sent = $deliveryStats->where('status', 'sent')->count();
    $enabledRules = collect($rules)->where('enabled', true)->count();
    $configuredChannels = collect($channels)->where('configured', true)->count();
@endphp

<div class="page-title-row">
    <span class="pt">Alerts</span>
    <span class="psub">Threshold rules, trigger history, and delivery checks</span>
    <div class="right"><a class="btn primary" href="{{ route('lookout.alerts.rules.create') }}">New rule</a></div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Rules</span><span class="k-val">{{ number_format($rulesTotal) }}</span><span class="k-sub">{{ number_format($enabledRules) }} enabled</span></div>
    <div class="kpi"><span class="k-lbl">Triggered</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">threshold audit entries</span></div>
    <div class="kpi"><span class="k-lbl">Sent deliveries</span><span class="k-val s-ok">{{ number_format($sent) }}</span><span class="k-sub">visible entries</span></div>
    <div class="kpi"><span class="k-lbl">Configured channels</span><span class="k-val">{{ number_format($configuredChannels) }}</span><span class="k-sub">email, slack, webhook</span></div>
</div>

<nav class="tabs mb12" aria-label="Alert sections">
    <a class="{{ $activeTab === 'rules' ? 'on' : '' }}" href="{{ route('lookout.alerts') }}">Rules</a>
    <a class="{{ $activeTab === 'history' ? 'on' : '' }}" href="{{ route('lookout.alerts', ['tab' => 'history']) }}">Trigger History</a>
    <a class="{{ $activeTab === 'delivery' ? 'on' : '' }}" href="{{ route('lookout.alerts', ['tab' => 'delivery']) }}">Delivery</a>
</nav>

@if($activeTab === 'rules')
    <div class="table-wrap"><div class="table-scroll"><table class="lk">
        <thead><tr><th>Name</th><th>Metric</th><th>Condition</th><th>Window</th><th>Cooldown</th><th>Channels</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($rules as $rule)
            @php
                $ruleChannels = is_array($rule['channels'] ?? null) ? $rule['channels'] : [];
                $enabled = (bool) ($rule['enabled'] ?? false);
            @endphp
            <tr>
                <td><span class="route truncate">{{ $rule['name'] ?? 'Threshold' }}</span></td>
                <td><span class="badge info">{{ $metrics[$rule['metric'] ?? ''] ?? ($rule['metric'] ?? 'metric') }}</span></td>
                <td class="mono">{{ $conditions[$rule['condition'] ?? ''] ?? ($rule['condition'] ?? '?') }} {{ $rule['value'] ?? '' }}</td>
                <td class="mono">{{ number_format((int) ($rule['window_minutes'] ?? 0)) }}m</td>
                <td class="mono">{{ number_format((int) ($rule['cooldown_minutes'] ?? 0)) }}m</td>
                <td>
                    <div class="flex gap6 wrap-actions">
                        @forelse($ruleChannels as $channel)
                            <span class="badge {{ ($channels[$channel]['configured'] ?? false) ? 'ok' : 'warn' }}">{{ $channels[$channel]['label'] ?? $channel }}</span>
                        @empty
                            <span class="badge neu">none</span>
                        @endforelse
                    </div>
                </td>
                <td><span class="badge {{ $enabled ? 'ok' : 'neu' }}">{{ $enabled ? 'Enabled' : 'Disabled' }}</span></td>
                <td>
                    <div class="row-actions">
                        <a class="btn sm" href="{{ route('lookout.alerts.rules.edit', $rule['id']) }}">Edit</a>
                        <form method="POST" action="{{ route('lookout.alerts.rules.toggle', $rule['id']) }}">@csrf<button class="btn sm" type="submit">{{ $enabled ? 'Disable' : 'Enable' }}</button></form>
                        <form method="POST" action="{{ route('lookout.alerts.rules.evaluate', $rule['id']) }}">@csrf<button class="btn sm" type="submit">Evaluate now</button></form>
                        <form method="POST" action="{{ route('lookout.alerts.rules.dispatch', $rule['id']) }}">@csrf<button class="btn sm" type="submit">Dispatch</button></form>
                        <form method="POST" action="{{ route('lookout.alerts.rules.delete', $rule['id']) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit">Delete</button></form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty"><h4>No threshold rules</h4><p>Create a rule to evaluate Lookout metrics and send alert deliveries.</p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@elseif($activeTab === 'delivery')
    <div class="table-wrap"><div class="table-scroll"><table class="lk">
        <thead><tr><th>Channel</th><th>Status</th><th>Destination</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach($channels as $name => $channel)
            <tr>
                <td><span class="route">{{ $channel['label'] }}</span></td>
                <td><span class="badge {{ $channel['configured'] ? 'ok' : 'warn' }}">{{ $channel['configured'] ? 'Configured' : 'Unconfigured' }}</span></td>
                <td class="mono subtle wrap-anywhere">{{ $channel['configured'] ? $channel['destination'] : 'Not configured' }}</td>
                <td>
                    @if($channel['configured'])
                        <form method="POST" action="{{ route('lookout.alerts.channels.test', $name) }}" class="row-actions">@csrf<button class="btn sm" type="submit">Test channel</button></form>
                    @else
                        <span class="subtle">Test unavailable</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>
@else
    <div class="filters">
        <div class="seg-toggle" data-filter-group="delivery"><button class="on" data-v="all">All</button><button data-v="sent">Sent</button><button data-v="failed">Failed</button><button data-v="skipped">Skipped</button></div>
        <div class="field"><label>Threshold</label><input data-filter="name" data-match="contains" placeholder="threshold name"></div>
        <span class="result-meta" data-total="{{ count($entries) }}">{{ number_format(count($entries)) }} shown</span>
    </div>

    <div class="table-wrap"><div class="table-scroll"><table class="lk" data-filterable>
        <thead><tr><th>Threshold</th><th>Metric</th><th>Condition</th><th>Deliveries</th><th>Time</th></tr></thead>
        <tbody>
        @forelse($entries as $entry)
            @php
                $details = $decodeDetails($entry['details'] ?? null);
                $deliveries = is_array($details['deliveries'] ?? null) ? $details['deliveries'] : [];
                $worst = collect($deliveries)->contains(fn ($d) => ($d['status'] ?? '') === 'failed') ? 'failed' : (collect($deliveries)->contains(fn ($d) => ($d['status'] ?? '') === 'sent') ? 'sent' : 'skipped');
            @endphp
            <tr class="row" data-delivery="{{ $worst }}" data-name="{{ strtolower(Payload::string($details, 'name', 'threshold')) }}" data-expand>
                <td><span class="route truncate">{{ Payload::string($details, 'name', 'Threshold') }}</span></td>
                <td><span class="badge neu">{{ Payload::string($details, 'metric', 'metric') }}</span></td>
                <td class="mono">{{ Payload::string($details, 'condition', '?') }} {{ Payload::number($details, 'value') }}</td>
                <td>
                    <div class="flex gap6 wrap-actions">
                        @forelse($deliveries as $delivery)
                            @php $status = $delivery['status'] ?? 'skipped'; @endphp
                            <span class="badge {{ $status === 'failed' ? 'err' : ($status === 'sent' ? 'ok' : 'neu') }}">{{ $delivery['channel'] ?? 'channel' }}: {{ $status }}</span>
                        @empty
                            <span class="badge neu">none</span>
                        @endforelse
                    </div>
                </td>
                <td class="t-time">{{ $entry['created_at'] ?? '' }}</td>
            </tr>
            <tr class="detail-row"><td colspan="5"><div class="detail-inner"><pre class="code">{{ json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></td></tr>
        @empty
            <tr><td colspan="5"><div class="empty"><h4>No alert history</h4><p>Threshold trigger audit entries will appear here when alerting is enabled and a threshold fires.</p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@endif
@endsection
