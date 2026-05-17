@extends('lookout::layouts.app')
@section('title', 'Logs')
@section('content')
@php
    $errorCount = 0; $warnCount = 0; $infoCount = 0; $debugCount = 0;
    foreach($logs as $log) {
        $p = json_decode($log['payload'], true) ?? [];
        $level = $p['level'] ?? 'info';
        if (in_array($level, ['error', 'critical', 'emergency'])) $errorCount++;
        elseif (in_array($level, ['warning', 'alert'])) $warnCount++;
        elseif ($level === 'debug') $debugCount++;
        else $infoCount++;
    }
@endphp

<div class="space-y-6">
    <h1 class="page-title">Logs</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Total Entries</div>
            <div class="stat-value text-slate-900">{{ number_format($total) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Errors</div>
            <div class="stat-value {{ $errorCount > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($errorCount) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b">
            <div class="stat-label">Warnings</div>
            <div class="stat-value {{ $warnCount > 0 ? 'text-amber-600' : 'text-green-600' }}">{{ number_format($warnCount) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #0ea5e9">
            <div class="stat-label">Info/Debug</div>
            <div class="stat-value text-slate-900">{{ number_format($infoCount + $debugCount) }}</div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Recent Log Entries</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ number_format($total) }} entries</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Level</th>
                    <th class="text-left px-5 py-3">Message</th>
                    <th class="text-left px-5 py-3">Channel</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($logs as $log)
                    @php
                        $p = json_decode($log['payload'], true) ?? [];
                        $level = $p['level'] ?? 'info';
                        $levelBadge = match($level) {
                            'error', 'critical', 'emergency' => 'badge-red',
                            'warning', 'alert' => 'badge-amber',
                            'notice', 'info' => 'badge-blue',
                            default => 'badge-gray'
                        };
                    @endphp
                    <tr>
                        <td class="px-5 py-3">
                            <span class="badge {{ $levelBadge }}">{{ strtoupper($level) }}</span>
                        </td>
                        <td class="px-5 py-3 text-sm text-slate-600 max-w-md truncate">{{ $p['message'] ?? '&mdash;' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $p['channel'] ?? '&mdash;' }}</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $log['timestamp'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($logs))
            <div class="empty-state">No log entries recorded yet.</div>
        @endif
    </div>
</div>
@endsection
