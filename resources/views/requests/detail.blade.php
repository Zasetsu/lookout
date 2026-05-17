@extends('lookout::layouts.app')
@section('title', 'Request Detail')
@section('content')
<div class="space-y-5">
    <div class="breadcrumb">
        <a href="{{ route('lookout.requests') }}">Requests</a>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700">Detail</span>
    </div>

    @if($trace)
        <div class="section-card">
            <div class="px-5 py-4 flex items-center gap-4 border-b border-gray-100">
                <span class="method-badge method-{{ $trace['method'] ?? 'GET' }}">{{ $trace['method'] ?? 'GET' }}</span>
                <span class="font-mono text-sm font-medium">{{ $trace['name'] }}</span>
                <span class="badge {{ ($trace['response_status'] ?? 200) >= 400 ? 'badge-red' : 'badge-green' }}">{{ $trace['response_status'] ?? 'N/A' }}</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-0 divide-x divide-gray-100">
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">Status</div>
                    <div class="text-sm font-medium">{{ $trace['status'] }}</div>
                </div>
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">Duration</div>
                    <div class="text-sm font-medium font-mono {{ ($trace['duration'] ?? 0) > 1000 ? 'text-red-600' : '' }}">{{ number_format($trace['duration'] ?? 0) }} ms</div>
                </div>
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">Memory</div>
                    <div class="text-sm font-medium font-mono">{{ $trace['memory_peak'] ? number_format($trace['memory_peak'] / 1048576, 1) . ' MB' : '—' }}</div>
                </div>
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">IP</div>
                    <div class="text-sm font-mono">{{ $trace['ip'] ?? '—' }}</div>
                </div>
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">Time</div>
                    <div class="text-sm text-slate-500">{{ $trace['timestamp'] }}</div>
                </div>
            </div>
        </div>

        @if($trace['url'] ?? null)
            <div class="px-4 py-2.5 bg-slate-800 rounded-lg text-xs font-mono text-slate-300 overflow-x-auto">{{ $trace['url'] }}</div>
        @endif

        <div class="section-card">
            <div class="section-header">Event Timeline ({{ count($events) }})</div>
            <div class="p-5">
                @if(!empty($events))
                    <div class="space-y-0">
                        @foreach($events as $event)
                            <div class="event-timeline-item event-{{ $event['event_type'] }}">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="badge
                                            @switch($event['event_type'])
                                                @case('query') badge-blue @break
                                                @case('exception') badge-red @break
                                                @case('cache') badge-green @break
                                                @case('outgoing_http') badge-purple @break
                                                @case('mail') badge-amber @break
                                                @case('notification') badge-purple @break
                                                @case('log') badge-gray @break
                                                @case('job_failed') badge-red @break
                                                @case('job_processed') badge-green @break
                                                @default badge-gray
                                            @endswitch
                                        ">{{ $event['event_type'] }}</span>
                                        @if($event['duration'])
                                            <span class="font-mono text-xs {{ $event['duration'] > 500 ? 'duration-warn' : 'text-slate-400' }}">{{ number_format($event['duration']) }} ms</span>
                                        @endif
                                    </div>
                                    <span class="text-[11px] text-slate-400">{{ $event['timestamp'] }}</span>
                                </div>
                                @if($event['labels'])
                                    <p class="text-sm text-slate-600 mb-1">{{ $event['labels'] }}</p>
                                @endif
                                <details>
                                    <summary class="text-xs text-slate-400 hover:text-slate-600 select-none">
                                        <span class="chevron">&#9654;</span> payload
                                    </summary>
                                    <pre class="mt-2 text-xs bg-slate-800 text-slate-300 p-3 rounded-lg overflow-auto max-h-64 font-mono leading-relaxed">{{ $event['payload'] }}</pre>
                                </details>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">No events for this trace.</div>
                @endif
            </div>
        </div>
    @else
        <div class="section-card"><div class="empty-state">Trace not found.</div></div>
    @endif
</div>
@endsection
