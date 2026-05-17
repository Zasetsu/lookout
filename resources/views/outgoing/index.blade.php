@extends('lookout::layouts.app')
@section('title', 'Outgoing HTTP')
@section('content')
@php
    $errorCount = 0; $totalReqs = count($requests);
    foreach($requests as $req) {
        $p = json_decode($req['payload'], true) ?? [];
        $s = $p['response_status'] ?? null;
        $failed = ($p['failed'] ?? false) === true;
        if ($failed || ($s !== null && $s >= 400)) $errorCount++;
    }
    $errorRate = $totalReqs > 0 ? round(($errorCount / $totalReqs) * 100, 1) : 0;
    $avgDuration = collect($requests)->avg(function($r) { $p = json_decode($r['payload'] ?? '{}', true) ?? []; return $p['duration_ms'] ?? 0; }) ?? 0;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Outgoing HTTP Requests</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Total Requests</div>
            <div class="stat-value text-slate-900">{{ number_format($total) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #0ea5e9">
            <div class="stat-label">Avg Duration</div>
            <div class="stat-value text-slate-900">{{ number_format($avgDuration) }}<span class="text-sm font-normal text-slate-400 ml-1">ms</span></div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Error Rate</div>
            <div class="stat-value {{ $errorCount > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $errorRate }}<span class="text-sm font-normal text-slate-400 ml-0.5">%</span></div>
        </div>
        <div class="stat-card" style="border-left-color: #8b5cf6">
            <div class="stat-label">Errors</div>
            <div class="stat-value {{ $errorCount > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($errorCount) }}</div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Recent Requests</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ number_format($total) }} requests</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Method</th>
                    <th class="text-left px-5 py-3">URL</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-right px-5 py-3">Duration</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($requests as $req)
                    @php $p = json_decode($req['payload'], true) ?? [] @endphp
                    <tr>
                        <td class="px-5 py-3"><span class="method-badge method-{{ $p['method'] ?? 'GET' }}">{{ $p['method'] ?? 'GET' }}</span></td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-600 max-w-md truncate">{{ $p['url'] ?? '&mdash;' }}</td>
                        <td class="px-5 py-3">
                            @php
                                $status = $p['response_status'] ?? 0;
                                $failed = ($p['failed'] ?? false) === true;
                            @endphp
                            @if($failed)
                                <span class="badge badge-red">Failed</span>
                            @else
                                <span class="badge {{ $status >= 500 ? 'badge-red' : ($status >= 400 ? 'badge-amber' : 'badge-green') }}">{{ $status }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-xs {{ ($p['duration_ms'] ?? 0) > 1000 ? 'duration-critical' : (($p['duration_ms'] ?? 0) > 500 ? 'duration-warn' : 'text-slate-500') }}">{{ isset($p['duration_ms']) ? number_format($p['duration_ms']) . ' ms' : '&mdash;' }}</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $req['timestamp'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($requests))
            <div class="empty-state">No outgoing HTTP requests recorded yet.</div>
        @endif
    </div>
</div>
@endsection
