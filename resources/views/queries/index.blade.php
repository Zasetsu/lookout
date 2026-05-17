@extends('lookout::layouts.app')

@section('title', 'Slow Queries')

@section('content')
@php
    $trendPoints = collect($trend ?? [])->pluck('count')->all();
    $trendTotal = array_sum($trendPoints);
    $bucketsData = $buckets['buckets'] ?? [];
    $bucketsMax = $buckets['max'] ?? 1;
    $bucketsTotal = $buckets['total'] ?? 0;
    $slowCount = count($queries);
    $slowRate = $bucketsTotal > 0 ? round(($slowCount / $bucketsTotal) * 100, 1) : 0;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Slow Queries</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #3b82f6">
            <div class="stat-label">Total Queries (24h)</div>
            <div class="stat-value text-slate-900">{{ number_format($trendTotal) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b">
            <div class="stat-label">Slow Queries</div>
            <div class="stat-value {{ $slowCount > 0 ? 'text-amber-600' : 'text-green-600' }}">{{ number_format($slowCount) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Threshold: >{{ $threshold }}ms</div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Slow Query Rate</div>
            <div class="stat-value {{ $slowRate > 10 ? 'text-red-600' : ($slowRate > 0 ? 'text-amber-600' : 'text-green-600') }}">{{ $slowRate }}<span class="text-sm font-normal text-slate-400 ml-0.5">%</span></div>
        </div>
        <div class="stat-card" style="border-left-color: #8b5cf6">
            <div class="stat-label">Sampled Queries</div>
            <div class="stat-value text-slate-900">{{ number_format($bucketsTotal) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="section-card">
            <div class="section-header">Query Volume Trend (24h)</div>
            <div class="p-4">
                @include('lookout::partials.sparkline', ['data' => $trendPoints, 'width' => 520, 'height' => 80, 'color' => '#3b82f6'])
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">Duration Distribution</div>
            <div class="p-5 space-y-3">
                @php
                $bucketColors = ['0-10ms' => '#22c55e', '10-50ms' => '#0ea5e9', '50-100ms' => '#6366f1', '100-500ms' => '#f59e0b', '500-1000ms' => '#f97316', '1s+' => '#ef4444'];
                @endphp
                @foreach($bucketsData as $label => $count)
                    @php $pct = $bucketsTotal > 0 ? round(($count / $bucketsTotal) * 100, 1) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500">{{ $label }}</span>
                            <span class="font-medium text-slate-700">{{ number_format($count) }} ({{ $pct }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full" style="width:{{ $pct }}%; background:{{ $bucketColors[$label] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Slow Queries (>{{ $threshold }}ms)</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ count($queries) }} queries</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Query</th>
                    <th class="text-right px-5 py-3">Duration</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($queries as $query)
                    <tr>
                        <td class="px-5 py-3">
                            <details>
                                <summary class="cursor-pointer text-sm text-slate-700 hover:text-slate-900 select-none">
                                    <span class="chevron">&#9654;</span>
                                    <span class="font-mono text-xs">{{ $query['labels'] ?? 'Query' }}</span>
                                </summary>
                                <pre class="mt-2 text-xs bg-slate-800 text-slate-300 p-3 rounded-lg overflow-auto max-h-64 font-mono leading-relaxed">{{ $query['payload'] }}</pre>
                            </details>
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-xs {{ ($query['duration'] ?? 0) > 1000 ? 'duration-critical' : (($query['duration'] ?? 0) > 500 ? 'duration-warn' : 'text-slate-500') }}">{{ number_format($query['duration'] ?? 0) }} ms</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $query['timestamp'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($queries))
            <div class="empty-state">No slow queries recorded yet.</div>
        @endif
    </div>
</div>
@endsection
