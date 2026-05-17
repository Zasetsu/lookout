@extends('lookout::layouts.app')
@section('title', 'Cache')
@section('content')
@php
    $trendPoints = collect($trend ?? [])->pluck('count')->all();
    $hitRate = $stats['hit_rate'] ?? 0;
    $missRate = 100 - $hitRate;
    $hits = $stats['hits'] ?? 0;
    $misses = $stats['misses'] ?? 0;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Cache</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Hit Rate</div>
            <div class="stat-value {{ $hitRate >= 80 ? 'text-green-600' : ($hitRate >= 50 ? 'text-amber-600' : 'text-red-600') }}">{{ number_format($hitRate, 1) }}<span class="text-sm font-normal text-slate-400 ml-0.5">%</span></div>
        </div>
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Hits</div>
            <div class="stat-value text-green-600">{{ number_format($hits) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Misses</div>
            <div class="stat-value {{ $misses > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($misses) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #3b82f6">
            <div class="stat-label">Writes</div>
            <div class="stat-value text-blue-600">{{ number_format($stats['writes'] ?? 0) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="section-card">
            <div class="section-header">Cache Operations (24h)</div>
            <div class="p-4">
                @include('lookout::partials.sparkline', ['data' => $trendPoints, 'width' => 520, 'height' => 80, 'color' => '#22c55e'])
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">Hit / Miss Ratio</div>
            <div class="p-5 flex items-center justify-center">
                @if(($stats['total'] ?? 0) > 0)
                    @php
                        $total = $stats['total'];
                        $hitAngle = ($hits / $total) * 360;
                        $cx = 80; $cy = 80; $r = 60; $sw = 20;
                        $hitEndX = $cx + $r * cos(deg2rad($hitAngle - 90));
                        $hitEndY = $cy + $r * sin(deg2rad($hitAngle - 90));
                        $largeArc = $hitAngle > 180 ? 1 : 0;
                    @endphp
                    <svg width="180" height="180" viewBox="0 0 160 160">
                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="#fee2e2" stroke-width="{{ $sw }}" />
                        <path d="M {{ $cx }},{{ $cy - $r }} A {{ $r }},{{ $r }} 0 {{ $largeArc }},1 {{ $hitEndX }},{{ $hitEndY }}" fill="none" stroke="#dcfce7" stroke-width="{{ $sw }}" stroke-linecap="butt" />
                        <text x="{{ $cx }}" y="{{ $cy - 8 }}" text-anchor="middle" font-size="22" font-weight="700" fill="#16a34a">{{ $hitRate }}%</text>
                        <text x="{{ $cx }}" y="{{ $cy + 12 }}" text-anchor="middle" font-size="11" fill="#64748b">hit rate</text>
                    </svg>
                    <div class="flex gap-6 ml-6">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-sm bg-green-100 border border-green-300"></div>
                            <span class="text-xs text-slate-500">Hits ({{ number_format($hits) }})</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-sm bg-red-100 border border-red-300"></div>
                            <span class="text-xs text-slate-500">Misses ({{ number_format($misses) }})</span>
                        </div>
                    </div>
                @else
                    <div class="empty-state text-xs">No cache operations yet</div>
                @endif
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">Recent Cache Operations</div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Operation</th>
                    <th class="text-left px-5 py-3">Key</th>
                    <th class="text-left px-5 py-3">Store</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($events as $event)
                    @php $p = json_decode($event['payload'], true) ?? [] @endphp
                    <tr>
                        <td class="px-5 py-3">
                            @switch($p['operation'] ?? '')
                                @case('cache_hit')
                                    <span class="badge badge-green">Hit</span>
                                    @break
                                @case('cache_miss')
                                    <span class="badge badge-red">Miss</span>
                                    @break
                                @case('cache_write')
                                    <span class="badge badge-blue">Write</span>
                                    @break
                                @case('cache_forget')
                                    <span class="badge badge-gray">Forget</span>
                                    @break
                                @default
                                    <span class="badge badge-gray">{{ $p['operation'] ?? 'unknown' }}</span>
                            @endswitch
                        </td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $p['key'] ?? '&mdash;' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $p['store'] ?? '&mdash;' }}</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $event['timestamp'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($events))
            <div class="empty-state">No cache operations recorded yet.</div>
        @endif
    </div>
</div>
@endsection
