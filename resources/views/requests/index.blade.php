@extends('lookout::layouts.app')
@section('title', 'Requests')
@section('content')
@php
    $volumePoints = collect($volume ?? [])->pluck('count')->map(fn ($v) => (int) $v)->all();
    $totalReqs = array_sum($volumePoints);
    $avgDuration = (float) ($summary['avg_duration'] ?? 0);
    $sd = $statusDist['distribution'] ?? [];
    $sdTotal = (int) ($statusDist['total'] ?? 0);
    $errorTotal = (int) ($sd['4xx'] ?? 0) + (int) ($sd['5xx'] ?? 0);
    $errorRate = $sdTotal > 0 ? round(($errorTotal / $sdTotal) * 100, 2) : 0;
    $successRate = $sdTotal > 0 ? round(100 - $errorRate, 2) : 100;
    $barValues = implode(',', $volumePoints !== [] ? $volumePoints : [0]);
    $statusFilter = request('status', '');
    $methodFilter = request('method', '');
@endphp

<div class="page-title-row">
    <span class="pt">Requests</span>
    <span class="psub">HTTP traces · sampled traffic · last 24 hours</span>
    <div class="right"><a class="btn sm" href="{{ route('lookout.requests') }}">Reset</a></div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(5,1fr)">
    <div class="kpi"><span class="k-lbl">Total</span><span class="k-val">{{ number_format($totalReqs) }}</span><span class="k-sub">requests in chart window</span></div>
    <div class="kpi"><span class="k-lbl">Avg duration</span><span class="k-val">{{ number_format($avgDuration) }}<span class="u">ms</span></span><span class="k-sub">all sampled requests</span></div>
    <div class="kpi"><span class="k-lbl">Success rate</span><span class="k-val {{ $successRate < 99 ? 's-warn' : 's-ok' }}">{{ $successRate }}<span class="u">%</span></span><span class="k-sub">{{ number_format($sd['2xx'] ?? 0) }} 2xx responses</span></div>
    <div class="kpi"><span class="k-lbl">Errors</span><span class="k-val {{ $errorTotal > 0 ? 's-err' : '' }}">{{ number_format($errorTotal) }}</span><span class="k-sub">4xx and 5xx responses</span></div>
    <div class="kpi"><span class="k-lbl">Stored traces</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">matching current filter</span></div>
</div>

<div class="grid mb12" style="grid-template-columns:1.35fr 1fr">
    <div class="panel">
        <div class="panel-h"><h3>Request volume</h3><span class="sub">requests per hour</span></div>
        <div class="panel-b"><div class="js-bars" data-tipunit="req" data-values="{{ $barValues }}" data-x="oldest|now"></div></div>
    </div>
    <div class="panel">
        <div class="panel-h"><h3>Status distribution</h3><span class="sub">{{ number_format($sdTotal) }} responses</span></div>
        <div class="panel-b">
            @php
                $two = (int) ($sd['2xx'] ?? 0); $three = (int) ($sd['3xx'] ?? 0); $four = (int) ($sd['4xx'] ?? 0); $five = (int) ($sd['5xx'] ?? 0);
                $pct = fn ($value) => $sdTotal > 0 ? max(0.5, round(($value / $sdTotal) * 100, 1)) : 0;
            @endphp
            <div class="seg">
                <span class="s2" style="width:{{ $pct($two) }}%"></span>
                <span class="s3" style="width:{{ $pct($three) }}%"></span>
                <span class="s4" style="width:{{ $pct($four) }}%"></span>
                <span class="s5" style="width:{{ $pct($five) }}%"></span>
            </div>
            <div class="seg-legend">
                <span class="li"><i class="sw" style="background:var(--c2xx)"></i>2xx <b>{{ number_format($two) }}</b></span>
                <span class="li"><i class="sw" style="background:var(--c3xx)"></i>3xx <b>{{ number_format($three) }}</b></span>
                <span class="li"><i class="sw" style="background:var(--c4xx)"></i>4xx <b>{{ number_format($four) }}</b></span>
                <span class="li"><i class="sw" style="background:var(--c5xx)"></i>5xx <b>{{ number_format($five) }}</b></span>
            </div>
        </div>
    </div>
</div>

<form method="GET" action="{{ route('lookout.requests') }}" class="filters">
    <div class="seg-toggle">
        <button type="submit" name="status" value="" class="{{ $statusFilter === '' ? 'on' : '' }}">All</button>
        <button type="submit" name="status" value="success" class="{{ $statusFilter === 'success' ? 'on' : '' }}">Success</button>
        <button type="submit" name="status" value="error" class="{{ $statusFilter === 'error' ? 'on' : '' }}">Errors</button>
    </div>
    <div class="field">
        <label>Method</label>
        <select name="method">
            <option value="">Any</option>
            @foreach(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'] as $method)
                <option value="{{ $method }}" @selected($methodFilter === $method)>{{ $method }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Route</label><input name="route" value="{{ request('route') }}" placeholder="name or path"></div>
    <div class="field"><label>Status</label><input name="response_status" value="{{ request('response_status') }}" placeholder="500" style="width:64px"></div>
    <div class="field"><label>Min</label><input name="min_duration" value="{{ request('min_duration') }}" placeholder="ms" style="width:64px"></div>
    <div class="field"><label>Since</label><input name="since" value="{{ request('since') }}" placeholder="24h" style="width:76px"></div>
    <button class="btn primary" type="submit">Apply</button>
    <span class="result-meta" data-total="{{ $total }}">{{ number_format(count($traces)) }} of {{ number_format($total) }} shown</span>
</form>

<div class="table-wrap">
    <div class="table-scroll">
        <table class="lk request-table" data-filterable>
            <thead>
                <tr><th>Method</th><th>Route</th><th>Status</th><th class="num">Duration</th><th class="num">Memory</th><th>Time</th></tr>
            </thead>
            <tbody>
            @forelse($traces as $trace)
                @php
                    $code = (int) ($trace['response_status'] ?? 0);
                    $method = $trace['method'] ?? 'GET';
                    $duration = (int) ($trace['duration'] ?? 0);
                    $statusTone = $code >= 500 ? 'err' : ($code >= 400 ? 'warn' : ($code >= 300 ? 'info' : 'ok'));
                    $statusName = ($trace['status'] ?? 'success') === 'error' || $code >= 400 ? 'error' : 'success';
                @endphp
                <tr class="row" data-status="{{ $statusName }}" data-method="{{ strtolower($method) }}" data-route="{{ strtolower(($trace['name'] ?? '').' '.($trace['url'] ?? '')) }}" data-code="{{ $code }}" data-dur="{{ $duration }}" onclick="location.href='{{ route('lookout.request-detail', $trace['trace_id']) }}'">
                    <td><span class="badge {{ in_array($method, ['POST', 'PUT', 'PATCH'], true) ? 'ok' : ($method === 'DELETE' ? 'err' : 'info') }}">{{ $method }}</span></td>
                    <td><div class="stack"><span class="route truncate">{{ $trace['name'] }}</span><span class="sm truncate">{{ $trace['url'] ?? '' }}</span></div></td>
                    <td><span class="badge {{ $statusTone }}">{{ $code > 0 ? $code : 'n/a' }}</span></td>
                    <td class="num dur {{ $duration > 1000 ? 'vslow' : ($duration > 500 ? 'slow' : '') }}">{{ number_format($duration) }}ms</td>
                    <td class="num subtle">{{ isset($trace['memory_peak']) ? number_format($trace['memory_peak'] / 1024 / 1024, 1).'MB' : 'n/a' }}</td>
                    <td class="t-time">{{ $trace['timestamp'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><h4>No requests recorded</h4><p>Generate traffic or adjust the filters to see request traces.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
