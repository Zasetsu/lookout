@extends('lookout::layouts.app')

@section('title', 'Commands')

@section('content')
@php
    $successCount = collect($commands)->where('status', 'success')->count();
    $failCount = count($commands) - $successCount;
    $successRate = count($commands) > 0 ? round(($successCount / count($commands)) * 100, 1) : 100;
    $avgDuration = collect($commands)->avg('duration') ?? 0;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Commands</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Executed</div>
            <div class="stat-value text-slate-900">{{ number_format(count($commands)) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Success Rate</div>
            <div class="stat-value {{ $failCount > 0 ? 'text-amber-600' : 'text-green-600' }}">{{ $successRate }}<span class="text-sm font-normal text-slate-400 ml-0.5">%</span></div>
        </div>
        <div class="stat-card" style="border-left-color: #0ea5e9">
            <div class="stat-label">Avg Duration</div>
            <div class="stat-value text-slate-900">{{ number_format($avgDuration, 1) }}<span class="text-sm font-normal text-slate-400 ml-1">ms</span></div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Failed</div>
            <div class="stat-value {{ $failCount > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($failCount) }}</div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Recent Executions</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ count($commands) }} executed</span>
        </div>
        @if(!empty($commands))
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3">Command</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-right px-5 py-3">Duration</th>
                        <th class="text-right px-5 py-3">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($commands as $cmd)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs font-medium">{{ $cmd['name'] }}</td>
                            <td class="px-5 py-3">
                                <span class="badge {{ $cmd['status'] === 'success' ? 'badge-green' : 'badge-red' }}">{{ $cmd['status'] }}</span>
                            </td>
                            <td class="px-5 py-3 text-right font-mono text-xs text-slate-500">{{ number_format($cmd['duration'] ?? 0) }} ms</td>
                            <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $cmd['timestamp'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-state">No commands recorded yet.</div>
        @endif
    </div>
</div>
@endsection
