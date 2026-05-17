@extends('lookout::layouts.app')
@section('title', 'Scheduled Tasks')
@section('content')
@php
    $successCount = collect($tasks)->where('status', 'success')->count();
    $failCount = count($tasks) - $successCount;
    $successRate = count($tasks) > 0 ? round(($successCount / count($tasks)) * 100, 1) : 100;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Scheduled Tasks</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Total Runs</div>
            <div class="stat-value text-slate-900">{{ number_format($total) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Success Rate</div>
            <div class="stat-value {{ $failCount > 0 ? 'text-amber-600' : 'text-green-600' }}">{{ $successRate }}<span class="text-sm font-normal text-slate-400 ml-0.5">%</span></div>
        </div>
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Successful</div>
            <div class="stat-value text-green-600">{{ number_format($successCount) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Failed</div>
            <div class="stat-value {{ $failCount > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($failCount) }}</div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Recent Runs</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ number_format($total) }} total</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Task</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-right px-5 py-3">Duration</th>
                    <th class="text-right px-5 py-3">Memory</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($tasks as $task)
                    <tr>
                        <td class="px-5 py-3 text-sm font-medium">{{ $task['name'] }}</td>
                        <td class="px-5 py-3">
                            <span class="badge {{ $task['status'] === 'success' ? 'badge-green' : 'badge-red' }}">{{ $task['status'] }}</span>
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-xs {{ ($task['duration'] ?? 0) > 1000 ? 'duration-critical' : (($task['duration'] ?? 0) > 500 ? 'duration-warn' : 'text-slate-500') }}">{{ $task['duration'] ? number_format($task['duration']) . ' ms' : '&mdash;' }}</td>
                        <td class="px-5 py-3 text-right font-mono text-xs text-slate-500">{{ $task['memory_peak'] ? number_format($task['memory_peak'] / 1024 / 1024, 2) . ' MB' : '&mdash;' }}</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $task['timestamp'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($tasks))
            <div class="empty-state">No scheduled tasks recorded yet.</div>
        @endif
    </div>
</div>
@endsection
