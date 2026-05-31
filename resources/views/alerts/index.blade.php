@extends('lookout::layouts.app')
@section('title', 'Alerts')
@section('content')
@php
    use Zasetsu\Lookout\Http\Support\Payload;
    $decodeDetails = fn ($details) => is_array($details) ? $details : Payload::decode($details);
    $deliveryStats = collect($entries)->flatMap(fn ($entry) => $decodeDetails($entry['details'] ?? null)['deliveries'] ?? []);
    $sent = $deliveryStats->where('status', 'sent')->count();
    $failed = $deliveryStats->where('status', 'failed')->count();
    $skipped = $deliveryStats->where('status', 'skipped')->count();
@endphp

<div class="page-title-row"><span class="pt">Alerts</span><span class="psub">Threshold trigger history and delivery telemetry</span></div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Triggered</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">threshold audit entries</span></div>
    <div class="kpi"><span class="k-lbl">Sent deliveries</span><span class="k-val s-ok">{{ number_format($sent) }}</span><span class="k-sub">visible entries</span></div>
    <div class="kpi"><span class="k-lbl">Failed deliveries</span><span class="k-val {{ $failed > 0 ? 's-err' : '' }}">{{ number_format($failed) }}</span><span class="k-sub">visible entries</span></div>
    <div class="kpi"><span class="k-lbl">Skipped</span><span class="k-val">{{ number_format($skipped) }}</span><span class="k-sub">unconfigured channels</span></div>
</div>

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
                <div class="flex gap6">
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
@endsection
