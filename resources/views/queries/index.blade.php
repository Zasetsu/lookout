@extends('lookout::layouts.app')
@section('title', 'Queries')
@section('content')
@php
    use Zasetsu\Lookout\Http\Support\Payload;
    $trendPoints = collect($trend ?? [])->pluck('count')->map(fn ($v) => (int) $v)->all();
    $trendTotal = array_sum($trendPoints);
    $bucketsTotal = (int) ($buckets['total'] ?? 0);
    $slowCount = collect($queries)->filter(fn ($q) => (int) ($q['duration'] ?? 0) >= $threshold)->count();
    $slowRate = $bucketsTotal > 0 ? round(($slowCount / $bucketsTotal) * 100, 1) : 0;
    $trendValues = implode(',', $trendPoints !== [] ? $trendPoints : [0]);
    $bucketValues = implode(',', array_values($buckets['buckets'] ?? [0]));
@endphp

<div class="page-title-row">
    <span class="pt">Queries</span>
    <span class="psub">Slow SQL and query duration distribution</span>
    <div class="right"><a class="btn sm" href="{{ route('lookout.queries') }}">Reset</a></div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Total queries</span><span class="k-val">{{ number_format($trendTotal) }}</span><span class="k-sub">last 24 hours</span></div>
    <div class="kpi"><span class="k-lbl">Current threshold</span><span class="k-val">{{ number_format($threshold) }}<span class="u">ms</span></span><span class="k-sub">minimum slow query</span></div>
    <div class="kpi"><span class="k-lbl">Shown slow queries</span><span class="k-val {{ $slowCount > 0 ? 's-warn' : '' }}">{{ number_format($slowCount) }}</span><span class="k-sub">{{ $slowRate }}% of bucketed sample</span></div>
    <div class="kpi"><span class="k-lbl">Bucketed sample</span><span class="k-val">{{ number_format($bucketsTotal) }}</span><span class="k-sub">latest query events</span></div>
</div>

<div class="grid mb12" style="grid-template-columns:1fr 1fr">
    <div class="panel"><div class="panel-h"><h3>Query trend</h3><span class="sub">events by hour</span></div><div class="panel-b"><div class="js-bars" data-values="{{ $trendValues }}" data-tipunit="queries" data-x="oldest|now"></div></div></div>
    <div class="panel"><div class="panel-h"><h3>Duration distribution</h3><span class="sub">latest sample</span></div><div class="panel-b"><div class="js-histo" data-values="{{ $bucketValues }}" data-x="{{ implode('|', array_keys($buckets['buckets'] ?? [])) }}"></div></div></div>
</div>

<form method="GET" action="{{ route('lookout.queries') }}" class="filters">
    <div class="field"><label>Threshold</label><input name="threshold" value="{{ $threshold }}" style="width:64px"> <span class="subtle">ms</span></div>
    <button class="btn primary" type="submit">Apply</button>
    <span class="result-meta" data-total="{{ count($queries) }}">{{ number_format(count($queries)) }} shown</span>
</form>

<div class="table-wrap">
    <div class="table-scroll">
        <table class="lk" data-filterable>
            <thead><tr><th>SQL</th><th>Connection</th><th class="num">Duration</th><th>Time</th></tr></thead>
            <tbody>
            @forelse($queries as $query)
                @php
                    $payload = Payload::decode($query['payload'] ?? null);
                    $sql = Payload::string($payload, 'sql', $query['labels'] ?? '');
                    $connection = Payload::string($payload, 'connection', 'default');
                    $duration = (int) ($query['duration'] ?? 0);
                @endphp
                <tr class="row" data-expand data-dur="{{ $duration }}" data-conn="{{ strtolower($connection) }}">
                    <td><span class="route mono truncate">{{ $sql }}</span></td>
                    <td><span class="badge neu">{{ $connection }}</span></td>
                    <td class="num dur {{ $duration > 1000 ? 'vslow' : ($duration >= $threshold ? 'slow' : '') }}">{{ number_format($duration) }}ms</td>
                    <td class="t-time">{{ $query['timestamp'] ?? '' }}</td>
                </tr>
                <tr class="detail-row"><td colspan="4"><div class="detail-inner"><pre class="code sql">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></td></tr>
            @empty
                <tr><td colspan="4"><div class="empty"><h4>No slow queries</h4><p>No query events are above the selected threshold.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
