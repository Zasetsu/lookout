@extends('lookout::layouts.app')
@section('title', 'Health')
@section('content')
@php
    $payload = is_array($health['payload_budget'] ?? null) ? $health['payload_budget'] : [];
@endphp
<div class="space-y-6">
    <h1 class="page-title">Health</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Status</div>
            <div class="stat-value text-green-600">{{ strtoupper($health['status'] ?? 'unknown') }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Storage connection</div>
        </div>
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Traces</div>
            <div class="stat-value text-slate-900">{{ number_format($health['trace_count'] ?? 0) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">{{ number_format($health['event_count'] ?? 0) }} events</div>
        </div>
        <div class="stat-card" style="border-left-color: #0ea5e9">
            <div class="stat-label">Storage Size</div>
            <div class="stat-value text-slate-900">{{ number_format($health['storage_size_mb'] ?? 0, 2) }}<span class="text-sm font-normal text-slate-400 ml-1">MB</span></div>
            <div class="text-[11px] text-slate-400 mt-1">{{ number_format($health['storage_size_bytes'] ?? 0) }} bytes</div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b">
            <div class="stat-label">Recent Requests</div>
            <div class="stat-value text-slate-900">{{ number_format($health['recent_requests_5m'] ?? 0) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Last 5 minutes</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="section-card">
            <div class="section-header">Retention</div>
            <dl class="p-5 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Retention Days</dt>
                    <dd class="mt-1 text-slate-900 font-medium">{{ number_format($health['retention_days'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Prune Chance</dt>
                    <dd class="mt-1 text-slate-900 font-medium">1 / {{ number_format($health['prune_chance'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Last Prune</dt>
                    <dd class="mt-1 text-slate-900 font-medium">{{ $health['last_prune_at'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Deleted Traces</dt>
                    <dd class="mt-1 text-slate-900 font-medium">{{ $health['last_prune_deleted_traces'] ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        <div class="section-card">
            <div class="section-header">Payload Budget</div>
            <dl class="p-5 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Max Body Bytes</dt>
                    <dd class="mt-1 text-slate-900 font-medium">{{ number_format($payload['max_request_body_bytes'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Bodies Captured</dt>
                    <dd class="mt-1 text-slate-900 font-medium">{{ number_format($payload['request_bodies'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Truncated Bodies</dt>
                    <dd class="mt-1 text-slate-900 font-medium">{{ number_format($payload['truncated_request_bodies'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Largest Original</dt>
                    <dd class="mt-1 text-slate-900 font-medium">{{ number_format($payload['largest_original_request_body_bytes'] ?? 0) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
