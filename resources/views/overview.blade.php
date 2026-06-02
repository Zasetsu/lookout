@extends('lookout::layouts.app')
@section('title', 'Overview')
@section('content')
@php
    $volumePoints = collect($volume ?? [])->pluck('count')->map(fn ($v) => (int) $v)->all();
    $totalReqs = array_sum($volumePoints);
    $reqsPerMin = $totalReqs > 0 ? round($totalReqs / 1440, 2) : 0;
    $avgDuration = (float) ($summary['avg_duration'] ?? 0);
    $sd = $statusDist['distribution'] ?? [];
    $sdTotal = (int) ($statusDist['total'] ?? 0);
    $two = (int) ($sd['2xx'] ?? 0);
    $three = (int) ($sd['3xx'] ?? 0);
    $four = (int) ($sd['4xx'] ?? 0);
    $five = (int) ($sd['5xx'] ?? 0);
    $errorTotal = $four + $five;
    $errorRate = $sdTotal > 0 ? round(($errorTotal / $sdTotal) * 100, 2) : 0;
    $spark = array_slice($volumePoints, -12);
    $sparkValues = implode(',', $spark !== [] ? $spark : [0]);
    $barValues = implode(',', $volumePoints !== [] ? $volumePoints : [0]);
    $barLabels = collect($volume ?? [])->pluck('hour')->map(fn ($h) => (string) $h)->implode('|');
    $topSlowRoutes = $summary['top_slow_routes'] ?? [];
@endphp

<div class="page-title-row">
    <span class="pt">Overview</span>
    <span class="psub">System health · {{ app()->environment() }} · last 24 hours</span>
    <div class="right">
        <a class="btn sm" href="{{ route('lookout.overview') }}">Refresh</a>
    </div>
</div>

<div class="deploy-latest mb12">
    <span class="deploy-latest-label">Latest deploy</span>
    @if($latestDeployMarker)
        <span class="deploy-version">{{ $latestDeployMarker['version'] ?? 'deploy' }}</span>
        <span class="deploy-env">{{ $latestDeployMarker['environment'] ?? app()->environment() }}</span>
        @if(! empty($latestDeployMarker['commit']))
            <span class="deploy-commit">{{ substr((string) $latestDeployMarker['commit'], 0, 7) }}</span>
        @endif
        <span class="deploy-time">{{ $latestDeployMarker['deployed_at'] ?? '' }}</span>
    @else
        <span class="deploy-empty">No deploy recorded</span>
    @endif
</div>

<div class="kpi-row" style="grid-template-columns:repeat(5,1fr)">
    <div class="kpi">
        <span class="k-lbl">Requests</span>
        <span class="k-val">{{ number_format($totalReqs) }}</span>
        <span class="k-sub">{{ $reqsPerMin }} req/min · last 24h</span>
    </div>
    <div class="kpi">
        <span class="k-lbl">Avg response</span>
        <span class="k-val">{{ number_format($avgDuration) }}<span class="u">ms</span></span>
        <span class="k-sub">Across sampled request traces</span>
    </div>
    <div class="kpi">
        <span class="k-lbl">Error rate</span>
        <span class="k-val {{ $errorTotal > 0 ? 's-err' : 's-ok' }}">{{ $errorRate }}<span class="u">%</span></span>
        <span class="k-sub">{{ number_format($errorTotal) }} errors of {{ number_format($sdTotal) }}</span>
    </div>
    <div class="kpi">
        <span class="k-lbl">Unresolved exceptions</span>
        <span class="k-val {{ ($summary['unresolved_groups'] ?? 0) > 0 ? 's-warn' : '' }}">{{ number_format($summary['unresolved_groups'] ?? 0) }}</span>
        <span class="k-sub">{{ number_format($summary['total_exceptions'] ?? 0) }} exception events</span>
    </div>
    <div class="kpi">
        <span class="k-lbl">Throughput</span>
        <span class="k-val">{{ number_format($reqsPerMin, 2) }}<span class="u">/min</span></span>
        <div class="js-spark" data-values="{{ $sparkValues }}" data-hi="3"></div>
    </div>
</div>

<div class="stat-tiles mb12">
    @foreach([
        ['label' => '2xx Success', 'value' => $two, 'color' => 'var(--c2xx)'],
        ['label' => '3xx Redirect', 'value' => $three, 'color' => 'var(--c3xx)'],
        ['label' => '4xx Client', 'value' => $four, 'color' => 'var(--c4xx)'],
        ['label' => '5xx Server', 'value' => $five, 'color' => 'var(--c5xx)', 'error' => true],
    ] as $tile)
        @php $pct = $sdTotal > 0 ? round(($tile['value'] / $sdTotal) * 100, 1) : 0; @endphp
        <div class="tile">
            <span class="bar-accent" style="background:{{ $tile['color'] }}"></span>
            <div class="t-lbl">{{ $tile['label'] }}</div>
            <div class="t-val">{{ number_format($tile['value']) }}</div>
            <div class="t-pct {{ ($tile['error'] ?? false) && $tile['value'] > 0 ? 's-err' : '' }}">{{ $pct }}% of traffic</div>
        </div>
    @endforeach
</div>

<div class="panel mb12">
    <div class="panel-h">
        <h3>Request volume</h3>
        <span class="sub">requests per hour · last 24h</span>
        <div class="right"><a class="more" href="{{ route('lookout.requests') }}">Open Requests</a></div>
    </div>
    <div class="panel-b">
        <div class="js-bars" data-tipunit="req" data-values="{{ $barValues }}" data-labels="{{ e($barLabels) }}" data-x="oldest|now"></div>
        @include('lookout::partials.deploy-markers', ['deployMarkers' => $deployMarkers ?? []])
    </div>
</div>

<div class="grid" style="grid-template-columns:1.45fr 1fr">
    <div class="panel">
        <div class="panel-h">
            <h3>Top slow routes</h3>
            <div class="right"><a class="more" href="{{ route('lookout.requests') }}">All requests</a></div>
        </div>
        <div class="table-scroll">
            <table class="lk">
                <thead><tr><th>Route</th><th class="num">Avg duration</th><th class="num">Requests</th></tr></thead>
                <tbody>
                @forelse($topSlowRoutes as $route)
                    <tr class="row" onclick="location.href='{{ route('lookout.requests', ['route' => $route['name'] ?? '']) }}'">
                        <td><div class="stack"><span class="route truncate">{{ $route['name'] ?? 'unknown' }}</span><span class="sm">request route</span></div></td>
                        <td class="num dur {{ ($route['avg_duration'] ?? 0) > 1000 ? 'vslow' : (($route['avg_duration'] ?? 0) > 500 ? 'slow' : '') }}">{{ number_format($route['avg_duration'] ?? 0, 1) }}ms</td>
                        <td class="num subtle">{{ number_format($route['count'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3"><div class="empty"><h4>No request data yet</h4><p>Lookout will populate slow routes after request traces are stored.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-h">
            <h3>Top exceptions</h3>
            <div class="right"><a class="more" href="{{ route('lookout.exceptions') }}">All exceptions</a></div>
        </div>
        <div class="table-scroll">
            <table class="lk">
                <thead><tr><th>Exception</th><th class="num">Count</th><th>Last seen</th></tr></thead>
                <tbody>
                @forelse($topExceptions as $ex)
                    <tr class="row" onclick="location.href='{{ route('lookout.exception-detail', $ex['id']) }}'">
                        <td><div class="stack"><span class="route mono truncate">{{ class_basename($ex['exception_class'] ?? 'Exception') }}</span><span class="sm truncate">{{ $ex['message'] ?? '' }}</span></div></td>
                        <td class="num"><span class="badge {{ ($ex['occurrence_count'] ?? 0) > 5 ? 'err' : 'warn' }}">{{ number_format($ex['occurrence_count'] ?? 0) }}</span></td>
                        <td class="t-time">{{ $ex['last_seen'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3"><div class="empty"><h4>No exceptions yet</h4><p>Exception groups will appear here when Lookout records failures.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
