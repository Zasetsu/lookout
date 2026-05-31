@extends('lookout::layouts.app')
@section('title', 'Jobs')
@section('content')
@php
    use Zasetsu\Lookout\Http\Support\Payload;
    $totalJobs = $processed_all + $failed_all;
    $failureRate = $totalJobs > 0 ? round(($failed_all / $totalJobs) * 100, 1) : 0;
    $avgDuration = collect($jobs)->avg('duration') ?? 0;
@endphp

<div class="page-title-row">
    <span class="pt">Jobs</span>
    <span class="psub">Queue processing · processed and failed job events</span>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Total jobs</span><span class="k-val">{{ number_format($totalJobs) }}</span><span class="k-sub">all recorded job events</span></div>
    <div class="kpi"><span class="k-lbl">Processed</span><span class="k-val s-ok">{{ number_format($processed_all) }}</span><span class="k-sub">{{ number_format($total_processed) }} in current page</span></div>
    <div class="kpi"><span class="k-lbl">Failed</span><span class="k-val {{ $failed_all > 0 ? 's-err' : '' }}">{{ number_format($failed_all) }}</span><span class="k-sub">{{ $failureRate }}% failure rate</span></div>
    <div class="kpi"><span class="k-lbl">Avg duration</span><span class="k-val">{{ number_format($avgDuration) }}<span class="u">ms</span></span><span class="k-sub">visible events</span></div>
</div>

<div class="filters">
    <div class="seg-toggle" data-filter-group="status"><button class="on" data-v="all">All</button><button data-v="processed">Processed</button><button data-v="failed">Failed</button></div>
    <div class="field"><label>Queue</label><input data-filter="queue" data-match="contains" placeholder="queue"></div>
    <div class="field"><label>Class</label><input data-filter="class" data-match="contains" placeholder="job class"></div>
    <span class="result-meta" data-total="{{ count($jobs) }}">{{ number_format(count($jobs)) }} shown</span>
</div>

<div class="table-wrap">
    <div class="table-scroll">
        <table class="lk" data-filterable>
            <thead><tr><th>Job</th><th>Queue</th><th>Status</th><th class="num">Attempts</th><th class="num">Duration</th><th>Time</th></tr></thead>
            <tbody>
            @forelse($jobs as $job)
                @php
                    $payload = Payload::decode($job['payload'] ?? null);
                    $jobClass = Payload::string($payload, 'job_class', $job['labels'] ?? 'Job');
                    $queue = Payload::string($payload, 'queue', 'default');
                    $attempts = Payload::number($payload, 'attempts', 1);
                    $failed = ($job['event_type'] ?? '') === 'job_failed';
                    $duration = (int) ($job['duration'] ?? 0);
                @endphp
                <tr class="row" data-status="{{ $failed ? 'failed' : 'processed' }}" data-queue="{{ strtolower($queue) }}" data-class="{{ strtolower($jobClass) }}" data-expand>
                    <td><div class="stack"><span class="route mono truncate">{{ class_basename($jobClass) }}</span><span class="sm truncate">{{ $jobClass }}</span></div></td>
                    <td><span class="badge neu">{{ $queue }}</span></td>
                    <td><span class="badge {{ $failed ? 'err' : 'ok' }}">{{ $failed ? 'failed' : 'processed' }}</span></td>
                    <td class="num">{{ number_format($attempts) }}</td>
                    <td class="num dur {{ $duration > 1000 ? 'slow' : '' }}">{{ number_format($duration) }}ms</td>
                    <td class="t-time">{{ $job['timestamp'] ?? '' }}</td>
                </tr>
                <tr class="detail-row"><td colspan="6"><div class="detail-inner"><pre class="code">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></td></tr>
            @empty
                <tr><td colspan="6"><div class="empty"><h4>No job events</h4><p>Processed and failed queue jobs will appear here.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
