@extends('lookout::layouts.app')
@section('title', 'Requests')
@section('content')
@php
    $volumePoints = collect($volume ?? [])->pluck('count')->all();
    $totalReqs = array_sum($volumePoints);
    $errorTotal = ($statusDist['distribution']['5xx'] ?? 0) + ($statusDist['distribution']['4xx'] ?? 0);
    $errorRate = $statusDist['total'] > 0 ? round(($errorTotal / $statusDist['total']) * 100, 1) : 0;
    $sd = $statusDist['distribution'] ?? [];
    $sdTotal = $statusDist['total'] ?? 0;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Requests</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Total (24h)</div>
            <div class="stat-value text-slate-900">{{ number_format($totalReqs) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #0ea5e9">
            <div class="stat-label">Avg Duration</div>
            <div class="stat-value text-slate-900">{{ number_format($summary['avg_duration'] ?? 0) }}<span class="text-sm font-normal text-slate-400 ml-1">ms</span></div>
        </div>
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Success Rate</div>
            <div class="stat-value {{ $errorRate > 5 ? 'text-amber-600' : ($errorRate > 0 ? 'text-amber-600' : 'text-green-600') }}">
                {{ $sdTotal > 0 ? round(100 - $errorRate, 1) : 100 }}<span class="text-sm font-normal text-slate-400 ml-0.5">%</span>
            </div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Errors (4xx/5xx)</div>
            <div class="stat-value {{ $errorTotal > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ number_format($errorTotal) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="section-card">
            <div class="section-header">Request Volume (24h)</div>
            <div class="p-4">
                @include('lookout::partials.sparkline', ['data' => $volumePoints, 'width' => 520, 'height' => 80, 'color' => '#6366f1'])
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">Status Distribution</div>
            <div class="p-5 space-y-3">
                @php
                $statusLabels = ['2xx' => '2xx Success', '3xx' => '3xx Redirect', '4xx' => '4xx Client Error', '5xx' => '5xx Server Error'];
                $statusColors = ['2xx' => ['bg' => '#22c55e', 'text' => 'text-green-600'], '3xx' => ['bg' => '#0ea5e9', 'text' => 'text-sky-600'], '4xx' => ['bg' => '#f59e0b', 'text' => 'text-amber-600'], '5xx' => ['bg' => '#ef4444', 'text' => 'text-red-600']];
                @endphp
                @foreach(['2xx', '3xx', '4xx', '5xx'] as $range)
                    @php $count = $sd[$range] ?? 0; $pct = $sdTotal > 0 ? round(($count / $sdTotal) * 100, 1) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500">{{ $statusLabels[$range] }}</span>
                            <span class="font-medium {{ $statusColors[$range]['text'] }}">{{ number_format($count) }} ({{ $pct }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full" style="width:{{ $pct }}%; background:{{ $statusColors[$range]['bg'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Recent Requests</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ number_format($total) }} total</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Method</th>
                    <th class="text-left px-5 py-3">Route</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-right px-5 py-3">Duration</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($traces as $trace)
                    <tr>
                        <td class="px-5 py-3"><span class="method-badge method-{{ $trace['method'] ?? 'GET' }}">{{ $trace['method'] ?? 'GET' }}</span></td>
                        <td class="px-5 py-3">
                            <a href="{{ route('lookout.request-detail', $trace['trace_id']) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800 hover:underline">{{ $trace['name'] }}</a>
                        </td>
                        <td class="px-5 py-3">
                            @php $code = $trace['response_status'] ?? 200 @endphp
                            <span class="badge {{ $code >= 500 ? 'badge-red' : ($code >= 400 ? 'badge-amber' : 'badge-green') }}">{{ $code }}</span>
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-xs {{ ($trace['duration'] ?? 0) > 1000 ? 'duration-critical' : (($trace['duration'] ?? 0) > 500 ? 'duration-warn' : 'text-slate-500') }}">{{ number_format($trace['duration'] ?? 0) }} ms</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $trace['timestamp'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($traces))
            <div class="empty-state">No requests recorded yet.</div>
        @endif
    </div>
</div>
@endsection
