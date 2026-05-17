@extends('lookout::layouts.app')
@section('title', 'Audit Log')
@section('content')
<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="page-title">Audit Log</h1>
            <p class="text-sm text-slate-500 mt-1">{{ number_format($total ?? 0) }} state changes and operational events</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('lookout.audit-export', ['format' => 'csv']) }}" class="badge badge-blue">Export CSV</a>
            <a href="{{ route('lookout.audit-export', ['format' => 'json']) }}" class="badge badge-gray">Export JSON</a>
        </div>
    </div>

    <div class="section-card">
        <form method="GET" action="{{ route('lookout.audit') }}" class="p-4 border-b border-gray-100 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold">Action</span>
                <input name="action" value="{{ $filters['action'] ?? '' }}" class="mt-1 rounded-md border-gray-300 text-sm" placeholder="threshold_triggered">
            </label>
            <label class="block">
                <span class="text-[11px] uppercase tracking-wide text-slate-500 font-semibold">Since</span>
                <input name="since" value="{{ $filters['since'] ?? '' }}" class="mt-1 rounded-md border-gray-300 text-sm" placeholder="2026-05-17 00:00:00">
            </label>
            <button class="badge badge-purple border-0 cursor-pointer">Filter</button>
            <a href="{{ route('lookout.audit') }}" class="badge badge-gray">Reset</a>
        </form>

        @if(!empty($entries))
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left px-5 py-3">Time</th>
                        <th class="text-left px-5 py-3">Action</th>
                        <th class="text-left px-5 py-3">Actor</th>
                        <th class="text-left px-5 py-3">IP</th>
                        <th class="text-left px-5 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($entries as $entry)
                        <tr>
                            <td class="px-5 py-3 text-xs text-slate-500 font-mono whitespace-nowrap">{{ $entry['created_at'] ?? '-' }}</td>
                            <td class="px-5 py-3"><span class="badge badge-blue">{{ $entry['action'] ?? 'unknown' }}</span></td>
                            <td class="px-5 py-3 text-xs text-slate-600">{{ $entry['user_id'] ?? '-' }}</td>
                            <td class="px-5 py-3 text-xs text-slate-600">{{ $entry['ip'] ?? '-' }}</td>
                            <td class="px-5 py-3">
                                <code class="text-[11px] text-slate-500 break-all">{{ is_array($entry['details'] ?? null) ? json_encode($entry['details']) : ($entry['details'] ?? '') }}</code>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-state">No audit entries match the current filters</div>
        @endif
    </div>
</div>
@endsection
