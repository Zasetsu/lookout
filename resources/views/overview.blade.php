@extends('lookout::layouts.app')
@section('title', 'Overview')
@section('content')
@php
    $volumePoints = collect($volume ?? [])->pluck('count')->all();
    $totalReqs = array_sum($volumePoints);
    $reqsPerMin = $totalReqs > 0 ? round($totalReqs / 1440, 2) : 0;
    $errorTotal = ($statusDist['distribution']['5xx'] ?? 0) + ($statusDist['distribution']['4xx'] ?? 0);
    $errorRate = $statusDist['total'] > 0 ? round(($errorTotal / $statusDist['total']) * 100, 1) : 0;

    $sd = $statusDist['distribution'] ?? [];
    $sdTotal = $statusDist['total'] ?? 0;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Overview</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Requests</div>
            <div class="stat-value text-slate-900">{{ number_format($totalReqs) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">{{ $reqsPerMin }} req/min (24h)</div>
        </div>
        <div class="stat-card" style="border-left-color: #0ea5e9">
            <div class="stat-label">Avg Response</div>
            <div class="stat-value text-slate-900">{{ number_format($summary['avg_duration'] ?? 0) }}<span class="text-sm font-normal text-slate-400 ml-1">ms</span></div>
            <div class="text-[11px] text-slate-400 mt-1">Last 24 hours</div>
        </div>
        <div class="stat-card" style="border-left-color: {{ $errorTotal > 0 ? '#ef4444' : '#22c55e' }}">
            <div class="stat-label">Error Rate</div>
            <div class="stat-value {{ $errorTotal > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $errorRate }}<span class="text-sm font-normal text-slate-400 ml-0.5">%</span></div>
            <div class="text-[11px] {{ $errorTotal > 0 ? 'text-red-400' : 'text-slate-400' }} mt-1">{{ $errorTotal }} errors (24h)</div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b">
            <div class="stat-label">Exceptions</div>
            <div class="stat-value {{ ($summary['unresolved_groups'] ?? 0) > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ number_format($summary['unresolved_groups'] ?? 0) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Unresolved groups</div>
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
            <div class="section-header">Status Code Distribution</div>
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
                @if($sdTotal === 0)
                    <div class="empty-state text-xs">No request data yet</div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="section-card" style="padding: 0;">
            <div class="section-header">Top Slow Routes</div>
            @if(!empty($summary['top_slow_routes']))
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left px-5 py-3">Route</th>
                            <th class="text-right px-5 py-3">Avg Duration</th>
                            <th class="text-right px-5 py-3">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($summary['top_slow_routes'] as $route)
                            <tr>
                                <td class="px-5 py-3 font-mono text-xs">{{ $route['name'] }}</td>
                                <td class="px-5 py-3 text-right {{ ($route['avg_duration'] ?? 0) > 500 ? 'duration-warn' : '' }}">
                                    <span class="font-mono text-xs">{{ number_format($route['avg_duration'] ?? 0, 1) }} ms</span>
                                </td>
                                <td class="px-5 py-3 text-right text-slate-500 text-xs">{{ number_format($route['count'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-state">No data yet</div>
            @endif
        </div>

        <div class="section-card" style="padding: 0;">
            <div class="section-header">Top Exceptions</div>
            @if(!empty($topExceptions))
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left px-5 py-3">Exception</th>
                            <th class="text-right px-5 py-3">Occurrences</th>
                            <th class="text-right px-5 py-3">Last Seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($topExceptions as $ex)
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('lookout.exception-detail', $ex['id']) }}" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline font-medium">{{ class_basename($ex['exception_class']) }}</a>
                                    <p class="text-[11px] text-slate-400 truncate max-w-xs">{{ $ex['message'] }}</p>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="badge {{ ($ex['occurrence_count'] ?? 0) > 5 ? 'badge-red' : 'badge-amber' }}">{{ number_format($ex['occurrence_count']) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $ex['last_seen'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-state">No exceptions yet</div>
            @endif
        </div>
    </div>
</div>
@endsection
