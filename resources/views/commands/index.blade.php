@extends('lookout::layouts.app')
@section('title', 'Commands')
@section('content')
@php
    $success = collect($commands)->where('status', 'success')->count();
    $failed = collect($commands)->where('status', 'error')->count();
    $avgDuration = collect($commands)->avg('duration') ?? 0;
@endphp

<div class="page-title-row">
    <span class="pt">Commands</span>
    <span class="psub">Artisan command traces and nested command calls</span>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Total commands</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">stored command traces</span></div>
    <div class="kpi"><span class="k-lbl">Successful</span><span class="k-val s-ok">{{ number_format($success) }}</span><span class="k-sub">visible commands</span></div>
    <div class="kpi"><span class="k-lbl">Failed</span><span class="k-val {{ $failed > 0 ? 's-err' : '' }}">{{ number_format($failed) }}</span><span class="k-sub">visible commands</span></div>
    <div class="kpi"><span class="k-lbl">Avg duration</span><span class="k-val">{{ number_format($avgDuration) }}<span class="u">ms</span></span><span class="k-sub">visible commands</span></div>
</div>

<div class="filters">
    <div class="seg-toggle" data-filter-group="status"><button class="on" data-v="all">All</button><button data-v="success">Success</button><button data-v="error">Failed</button></div>
    <div class="field"><label>Command</label><input data-filter="cmd" data-match="contains" placeholder="command name"></div>
    <span class="result-meta" data-total="{{ count($commands) }}">{{ number_format(count($commands)) }} shown</span>
</div>

<div class="table-wrap">
    <div class="table-scroll">
        <table class="lk" data-filterable>
            <thead><tr><th>Command</th><th>Status</th><th class="num">Duration</th><th class="num">Memory</th><th>Time</th></tr></thead>
            <tbody>
            @forelse($commands as $command)
                @php $status = $command['status'] ?? 'success'; $duration = (int) ($command['duration'] ?? 0); @endphp
                <tr class="row" data-status="{{ $status }}" data-cmd="{{ strtolower($command['name'] ?? '') }}">
                    <td><span class="route mono truncate">{{ $command['name'] ?? 'command' }}</span></td>
                    <td><span class="badge {{ $status === 'error' ? 'err' : 'ok' }}">{{ $status }}</span></td>
                    <td class="num dur {{ $duration > 1000 ? 'slow' : '' }}">{{ number_format($duration) }}ms</td>
                    <td class="num subtle">{{ isset($command['memory_peak']) ? number_format($command['memory_peak'] / 1024 / 1024, 1).'MB' : 'n/a' }}</td>
                    <td class="t-time">{{ $command['timestamp'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><h4>No command traces</h4><p>Sampled Artisan command traces will appear here.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
