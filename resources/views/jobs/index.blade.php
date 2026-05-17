@extends('lookout::layouts.app')
@section('title', 'Jobs')
@section('content')
@php
    $total = ($processed_all ?? 0) + ($failed_all ?? 0);
    $failRate = $total > 0 ? round((($failed_all ?? 0) / $total) * 100, 1) : 0;
    $passPct = $total > 0 ? round((($processed_all ?? 0) / $total) * 100, 1) : 100;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Jobs</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Total Jobs</div>
            <div class="stat-value text-slate-900">{{ number_format($total) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Processed</div>
            <div class="stat-value text-green-600">{{ number_format($processed_all ?? 0) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Failed</div>
            <div class="stat-value {{ ($failed_all ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($failed_all ?? 0) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b">
            <div class="stat-label">Fail Rate</div>
            <div class="stat-value {{ $failRate > 5 ? 'text-red-600' : ($failRate > 0 ? 'text-amber-600' : 'text-green-600') }}">{{ $failRate }}<span class="text-sm font-normal text-slate-400 ml-0.5">%</span></div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">Pass / Fail Ratio</div>
        <div class="p-5">
            @if($total > 0)
                <div class="w-full bg-gray-100 rounded-full h-6 overflow-hidden flex">
                    <div class="h-6 bg-green-500 flex items-center justify-center text-[11px] font-bold text-white" style="width:{{ $passPct }}%">
                        @if($passPct > 15){{ number_format($processed_all ?? 0) }} Passed @endif
                    </div>
                    <div class="h-6 bg-red-500 flex items-center justify-center text-[11px] font-bold text-white" style="width:{{ $failRate }}%">
                        @if($failRate > 15){{ number_format($failed_all ?? 0) }} Failed @endif
                    </div>
                </div>
                <div class="flex justify-between mt-2 text-[11px] text-slate-400">
                    <span>{{ $passPct }}% succeeded</span>
                    <span>{{ $failRate }}% failed</span>
                </div>
            @else
                <div class="empty-state text-xs">No job data yet</div>
            @endif
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Recent Jobs</span>
            <div class="flex gap-4 text-xs text-slate-400">
                <span>Processed: <span class="font-semibold text-green-600">{{ $total_processed }}</span></span>
                <span>Failed: <span class="font-semibold {{ $total_failed > 0 ? 'text-red-600' : 'text-slate-400' }}">{{ $total_failed }}</span></span>
            </div>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Job Class</th>
                    <th class="text-left px-5 py-3">Queue</th>
                    <th class="text-left px-5 py-3">Type</th>
                    <th class="text-right px-5 py-3">Duration</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($jobs as $job)
                    @php $payload = \Zasetsu\Lookout\Http\Support\Payload::decode($job['payload'] ?? null) @endphp
                    <tr>
                        <td class="px-5 py-3 text-sm font-medium">{{ $payload['job_class'] ?? $job['labels'] ?? 'Unknown' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $payload['queue'] ?? 'default' }}</td>
                        <td class="px-5 py-3">
                            @if($job['event_type'] === 'job_failed')
                                <span class="badge badge-red">Failed</span>
                            @else
                                <span class="badge badge-green">Processed</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-xs text-slate-500">{{ $job['duration'] ? number_format($job['duration']) . ' ms' : '&mdash;' }}</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $job['timestamp'] }}</td>
                    </tr>
                    @if($job['event_type'] === 'job_failed' && isset($payload['exception']))
                        <tr class="bg-red-50/50">
                            <td colspan="5" class="px-5 py-2 text-xs font-mono text-red-700">
                                {{ $payload['exception']['class'] ?? '' }}: {{ $payload['exception']['message'] ?? '' }}
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        @if(empty($jobs))
            <div class="empty-state">No jobs recorded yet.</div>
        @endif
    </div>
</div>
@endsection
