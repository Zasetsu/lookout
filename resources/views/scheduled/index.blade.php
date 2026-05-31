@extends('lookout::layouts.app')
@section('title', 'Scheduled')
@section('content')
@php
    $success = collect($tasks)->where('status', 'success')->count();
    $failed = collect($tasks)->where('status', 'error')->count();
    $avgDuration = collect($tasks)->avg('duration') ?? 0;
@endphp

<div class="page-title-row">
    <span class="pt">Scheduled Tasks</span>
    <span class="psub">Laravel scheduler runs and background completions</span>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Total runs</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">stored scheduled traces</span></div>
    <div class="kpi"><span class="k-lbl">Successful</span><span class="k-val s-ok">{{ number_format($success) }}</span><span class="k-sub">visible runs</span></div>
    <div class="kpi"><span class="k-lbl">Failed</span><span class="k-val {{ $failed > 0 ? 's-err' : '' }}">{{ number_format($failed) }}</span><span class="k-sub">visible runs</span></div>
    <div class="kpi"><span class="k-lbl">Avg duration</span><span class="k-val">{{ number_format($avgDuration) }}<span class="u">ms</span></span><span class="k-sub">visible runs</span></div>
</div>

<div class="filters">
    <div class="seg-toggle" data-filter-group="status"><button class="on" data-v="all">All</button><button data-v="success">Success</button><button data-v="error">Failed</button></div>
    <div class="field"><label>Task</label><input data-filter="task" data-match="contains" placeholder="command or name"></div>
    <span class="result-meta" data-total="{{ count($tasks) }}">{{ number_format(count($tasks)) }} shown</span>
</div>

<div class="table-wrap">
    <div class="table-scroll">
        <table class="lk" data-filterable>
            <thead><tr><th>Task</th><th>Status</th><th class="num">Duration</th><th class="num">Memory</th><th>Time</th></tr></thead>
            <tbody>
            @forelse($tasks as $task)
                @php $status = $task['status'] ?? 'success'; $duration = (int) ($task['duration'] ?? 0); @endphp
                <tr class="row" data-status="{{ $status }}" data-task="{{ strtolower($task['name'] ?? '') }}">
                    <td><span class="route mono truncate">{{ $task['name'] ?? 'scheduled task' }}</span></td>
                    <td><span class="badge {{ $status === 'error' ? 'err' : 'ok' }}">{{ $status }}</span></td>
                    <td class="num dur {{ $duration > 1000 ? 'slow' : '' }}">{{ number_format($duration) }}ms</td>
                    <td class="num subtle">{{ isset($task['memory_peak']) ? number_format($task['memory_peak'] / 1024 / 1024, 1).'MB' : 'n/a' }}</td>
                    <td class="t-time">{{ $task['timestamp'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><h4>No scheduled tasks</h4><p>Scheduler traces will appear here after scheduled tasks run.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
