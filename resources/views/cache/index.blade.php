@extends('lookout::layouts.app')
@section('title', 'Cache')
@section('content')
@php
    use Zasetsu\Lookout\Http\Support\Payload;
    $trendPoints = collect($trend ?? [])->pluck('count')->map(fn ($v) => (int) $v)->all();
    $hitRate = (float) ($stats['hit_rate'] ?? 0);
    $hits = (int) ($stats['hits'] ?? 0);
    $misses = (int) ($stats['misses'] ?? 0);
    $writes = (int) ($stats['writes'] ?? 0);
    $trendValues = implode(',', $trendPoints !== [] ? $trendPoints : [0]);
@endphp

<div class="page-title-row"><span class="pt">Cache</span><span class="psub">Cache hit, miss, write, and forget events</span></div>

<div class="kpi-row" style="grid-template-columns:repeat(5,1fr)">
    <div class="kpi"><span class="k-lbl">Hit rate</span><span class="k-val {{ $hitRate >= 80 ? 's-ok' : ($hitRate >= 50 ? 's-warn' : 's-err') }}">{{ number_format($hitRate, 1) }}<span class="u">%</span></span><span class="k-sub">hits vs misses</span></div>
    <div class="kpi"><span class="k-lbl">Hits</span><span class="k-val s-ok">{{ number_format($hits) }}</span><span class="k-sub">last 24 hours</span></div>
    <div class="kpi"><span class="k-lbl">Misses</span><span class="k-val {{ $misses > 0 ? 's-warn' : 's-ok' }}">{{ number_format($misses) }}</span><span class="k-sub">last 24 hours</span></div>
    <div class="kpi"><span class="k-lbl">Writes</span><span class="k-val">{{ number_format($writes) }}</span><span class="k-sub">last 24 hours</span></div>
    <div class="kpi"><span class="k-lbl">Events</span><span class="k-val">{{ number_format(count($events)) }}</span><span class="k-sub">latest visible</span></div>
</div>

<div class="panel mb12"><div class="panel-h"><h3>Cache operations</h3><span class="sub">events by hour · last 24h</span></div><div class="panel-b"><div class="js-bars" data-values="{{ $trendValues }}" data-tipunit="events" data-x="oldest|now"></div></div></div>

<div class="filters">
    <div class="seg-toggle" data-filter-group="op"><button class="on" data-v="all">All</button><button data-v="cache_hit">Hit</button><button data-v="cache_miss">Miss</button><button data-v="cache_write">Write</button><button data-v="cache_forget">Forget</button></div>
    <div class="field"><label>Store</label><input data-filter="store" data-match="contains" placeholder="store"></div>
    <div class="field"><label>Key</label><input data-filter="key" data-match="contains" placeholder="key prefix"></div>
    <span class="result-meta" data-total="{{ count($events) }}">{{ number_format(count($events)) }} shown</span>
</div>

<div class="table-wrap"><div class="table-scroll"><table class="lk" data-filterable>
    <thead><tr><th>Operation</th><th>Key</th><th>Store</th><th class="num">Duration</th><th>Time</th></tr></thead>
    <tbody>
    @forelse($events as $event)
        @php
            $p = Payload::decode($event['payload'] ?? null);
            $operation = Payload::string($p, 'operation', 'unknown');
            $key = Payload::string($p, 'key', '');
            $store = Payload::string($p, 'store', '');
            $tone = $operation === 'cache_hit' ? 'ok' : ($operation === 'cache_miss' ? 'warn' : ($operation === 'cache_write' ? 'info' : 'neu'));
        @endphp
        <tr class="row" data-op="{{ $operation }}" data-store="{{ strtolower($store) }}" data-key="{{ strtolower($key) }}">
            <td><span class="badge {{ $tone }}">{{ str_replace('cache_', '', $operation) }}</span></td>
            <td><span class="mono route truncate">{{ $key !== '' ? $key : 'n/a' }}</span></td>
            <td><span class="badge neu">{{ $store !== '' ? $store : 'default' }}</span></td>
            <td class="num dur">{{ isset($event['duration']) ? number_format($event['duration']).'ms' : 'n/a' }}</td>
            <td class="t-time">{{ $event['timestamp'] ?? '' }}</td>
        </tr>
    @empty
        <tr><td colspan="5"><div class="empty"><h4>No cache events</h4><p>Cache operations will appear here when Laravel emits cache events.</p></div></td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
