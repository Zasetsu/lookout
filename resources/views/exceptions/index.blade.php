@extends('lookout::layouts.app')

@section('title', 'Exceptions')

@section('content')
@php
    $trendPoints = collect($trend ?? [])->pluck('count')->all();
    $trendTotal = array_sum($trendPoints);
    $unresolved = $statusCounts['unresolved'] ?? 0;
    $resolved = $statusCounts['resolved'] ?? 0;
    $ignored = $statusCounts['ignored'] ?? 0;
    $allTotal = $unresolved + $resolved + $ignored;
@endphp

<div class="space-y-6">
    <h1 class="page-title">Exceptions</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card" style="border-left-color: #ef4444">
            <div class="stat-label">Total Groups</div>
            <div class="stat-value text-slate-900">{{ number_format($allTotal) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b">
            <div class="stat-label">Unresolved</div>
            <div class="stat-value {{ $unresolved > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($unresolved) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #8b5cf6">
            <div class="stat-label">Total Occurrences (24h)</div>
            <div class="stat-value text-slate-900">{{ number_format($trendTotal) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Resolved</div>
            <div class="stat-value text-green-600">{{ number_format($resolved) }}</div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">Exception Trend (24h)</div>
        <div class="p-4">
            @include('lookout::partials.sparkline', ['data' => $trendPoints, 'width' => 600, 'height' => 80, 'color' => '#ef4444'])
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Exception Groups</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ number_format($total) }} groups</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Exception</th>
                    <th class="text-left px-5 py-3">File</th>
                    <th class="text-right px-5 py-3">Occurrences</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-right px-5 py-3">Last Seen</th>
                    <th class="text-right px-5 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($groups as $group)
                    <tr>
                        <td class="px-5 py-3">
                            <a href="{{ route('lookout.exception-detail', $group['id']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">{{ $group['exception_class'] }}</a>
                            <p class="text-xs text-slate-400 mt-0.5 truncate max-w-md">{{ $group['message'] }}</p>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ basename($group['file']) }}:{{ $group['line'] }}</td>
                        <td class="px-5 py-3 text-right font-medium text-sm">{{ number_format($group['occurrence_count']) }}</td>
                        <td class="px-5 py-3">
                            @php
                                $statusMap = ['unresolved' => 'badge-red', 'resolved' => 'badge-green', 'ignored' => 'badge-gray'];
                            @endphp
                            <span class="badge {{ $statusMap[$group['status']] ?? 'badge-gray' }}">{{ $group['status'] }}</span>
                        </td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $group['last_seen'] }}</td>
                        <td class="px-5 py-3 text-right space-x-3">
                            @if($group['status'] === 'unresolved')
                                <form method="POST" action="{{ route('lookout.exception-resolve', $group['id']) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-green-600 hover:underline">Resolve</button>
                                </form>
                                <form method="POST" action="{{ route('lookout.exception-ignore', $group['id']) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-slate-400 hover:underline">Ignore</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($groups))
            <div class="empty-state">No exceptions recorded yet.</div>
        @endif
    </div>
</div>
@endsection
