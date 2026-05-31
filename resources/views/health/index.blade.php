@extends('lookout::layouts.app')
@section('title', 'Health')
@section('content')
@php
    $payload = $health['payload_budget'] ?? [];
    $budget = (int) ($payload['max_request_body_bytes'] ?? 0);
    $largest = (int) ($payload['largest_original_request_body_bytes'] ?? 0);
    $budgetPct = $budget > 0 ? min(100, round(($largest / $budget) * 100)) : 0;
@endphp

<div class="page-title-row">
    <span class="pt">Health</span>
    <span class="psub">Storage, retention, and capture budget checks</span>
    <div class="right"><a class="btn sm" href="{{ route('lookout.health') }}">Re-run check</a></div>
</div>

<div class="health-hero {{ ($health['status'] ?? 'ok') === 'ok' ? '' : 'bad' }}">
    <div class="hb-ic"><span class="badge {{ ($health['status'] ?? 'ok') === 'ok' ? 'ok' : 'err' }}">{{ $health['status'] ?? 'unknown' }}</span></div>
    <div><div class="ht">Lookout storage is {{ $health['status'] ?? 'unknown' }}</div><div class="hs">{{ $health['storage_driver'] ?? 'unknown' }} driver on {{ $health['storage_connection'] ?? 'unknown' }} connection</div></div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Traces</span><span class="k-val">{{ number_format($health['trace_count'] ?? 0) }}</span><span class="k-sub">stored traces</span></div>
    <div class="kpi"><span class="k-lbl">Events</span><span class="k-val">{{ number_format($health['event_count'] ?? 0) }}</span><span class="k-sub">stored child events</span></div>
    <div class="kpi"><span class="k-lbl">Recent requests</span><span class="k-val">{{ number_format($health['recent_requests_5m'] ?? 0) }}</span><span class="k-sub">last 5 minutes</span></div>
    <div class="kpi"><span class="k-lbl">Retention</span><span class="k-val">{{ number_format($health['retention_days'] ?? 0) }}<span class="u">d</span></span><span class="k-sub">prune chance 1/{{ $health['prune_chance'] ?? 0 }}</span></div>
</div>

<div class="grid split" style="grid-template-columns:1fr 1fr">
    <div class="panel">
        <div class="panel-h"><h3>Storage</h3></div>
        <div class="panel-b">
            <dl class="def-list">
                <dt>Driver</dt><dd>{{ $health['storage_driver'] ?? 'n/a' }}</dd>
                <dt>Connection</dt><dd>{{ $health['storage_connection'] ?? 'n/a' }}</dd>
                <dt>Size</dt><dd>{{ $health['storage_size_mb'] !== null ? number_format($health['storage_size_mb'], 2).' MB' : 'n/a for host-managed SQL' }}</dd>
                <dt>Last prune</dt><dd>{{ $health['last_prune_at'] ?? 'never' }}</dd>
                <dt>Deleted traces</dt><dd>{{ $health['last_prune_deleted_traces'] ?? 'n/a' }}</dd>
            </dl>
        </div>
    </div>
    <div class="panel">
        <div class="panel-h"><h3>Request body budget</h3><span class="sub">{{ number_format($budget) }} bytes max</span></div>
        <div class="panel-b">
            <div class="bar-meter {{ $budgetPct > 90 ? 'err' : ($budgetPct > 70 ? 'warn' : '') }}"><i style="width:{{ $budgetPct }}%"></i></div>
            <dl class="def-list mt16">
                <dt>Captured bodies</dt><dd>{{ number_format($payload['request_bodies'] ?? 0) }}</dd>
                <dt>Truncated bodies</dt><dd>{{ number_format($payload['truncated_request_bodies'] ?? 0) }}</dd>
                <dt>Largest original</dt><dd>{{ number_format($largest) }} bytes</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
