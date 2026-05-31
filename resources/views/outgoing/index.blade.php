@extends('lookout::layouts.app')
@section('title', 'Outgoing HTTP')
@section('content')
@php
    use Zasetsu\Lookout\Http\Support\Payload;
    $payloads = collect($requests)->map(fn ($r) => Payload::decode($r['payload'] ?? null));
    $failed = $payloads->filter(fn ($p) => Payload::bool($p, 'failed') || (int) Payload::number($p, 'response_status') >= 400)->count();
    $avgDuration = $payloads->avg(fn ($p) => Payload::number($p, 'duration_ms')) ?? 0;
    $errorRate = count($requests) > 0 ? round(($failed / count($requests)) * 100, 1) : 0;
@endphp

<div class="page-title-row"><span class="pt">Outgoing HTTP</span><span class="psub">External HTTP calls and connection failures</span></div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Total requests</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">stored outgoing events</span></div>
    <div class="kpi"><span class="k-lbl">Avg duration</span><span class="k-val">{{ number_format($avgDuration) }}<span class="u">ms</span></span><span class="k-sub">visible events</span></div>
    <div class="kpi"><span class="k-lbl">Failures</span><span class="k-val {{ $failed > 0 ? 's-err' : '' }}">{{ number_format($failed) }}</span><span class="k-sub">{{ $errorRate }}% visible error rate</span></div>
    <div class="kpi"><span class="k-lbl">Visible</span><span class="k-val">{{ number_format(count($requests)) }}</span><span class="k-sub">latest entries</span></div>
</div>

<div class="filters">
    <div class="seg-toggle" data-filter-group="state"><button class="on" data-v="all">All</button><button data-v="ok">OK</button><button data-v="failed">Failed</button></div>
    <div class="field"><label>Host</label><input data-filter="host" data-match="contains" placeholder="hostname"></div>
    <span class="result-meta" data-total="{{ count($requests) }}">{{ number_format(count($requests)) }} shown</span>
</div>

<div class="table-wrap"><div class="table-scroll"><table class="lk" data-filterable>
    <thead><tr><th>Request</th><th>Status</th><th class="num">Duration</th><th>Time</th></tr></thead>
    <tbody>
    @forelse($requests as $request)
        @php
            $p = Payload::decode($request['payload'] ?? null);
            $method = Payload::string($p, 'method', 'GET');
            $url = Payload::string($p, 'url', $request['labels'] ?? '');
            $host = parse_url($url, PHP_URL_HOST) ?: $url;
            $status = (int) Payload::number($p, 'response_status');
            $isFailed = Payload::bool($p, 'failed') || $status >= 400 || $status === 0;
            $duration = (int) Payload::number($p, 'duration_ms', (float) ($request['duration'] ?? 0));
        @endphp
        <tr class="row" data-state="{{ $isFailed ? 'failed' : 'ok' }}" data-host="{{ strtolower((string) $host) }}" data-expand>
            <td><div class="stack"><span class="route mono truncate">{{ $method }} {{ $url }}</span><span class="sm truncate">{{ $host }}</span></div></td>
            <td><span class="badge {{ $isFailed ? 'err' : 'ok' }}">{{ $isFailed && $status === 0 ? 'failed' : $status }}</span></td>
            <td class="num dur {{ $duration > 1000 ? 'slow' : '' }}">{{ number_format($duration) }}ms</td>
            <td class="t-time">{{ $request['timestamp'] ?? '' }}</td>
        </tr>
        <tr class="detail-row"><td colspan="4"><div class="detail-inner"><pre class="code">{{ json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></td></tr>
    @empty
        <tr><td colspan="4"><div class="empty"><h4>No outgoing HTTP events</h4><p>Laravel HTTP client events will appear here.</p></div></td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
