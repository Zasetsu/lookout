@extends('lookout::layouts.app')

@section('title', 'Exception Detail')

@section('content')
<div class="space-y-5">
    <div class="breadcrumb">
        <a href="{{ route('lookout.exceptions') }}">Exceptions</a>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700">Detail</span>
    </div>

    @if($group)
        <div class="section-card">
            <div class="px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    @php $statusMap = ['unresolved' => 'badge-red', 'resolved' => 'badge-green', 'ignored' => 'badge-gray'] @endphp
                    <span class="badge {{ $statusMap[$group['status']] ?? 'badge-gray' }}">{{ $group['status'] }}</span>
                    <h2 class="text-base font-semibold {{ $group['status'] === 'unresolved' ? 'text-red-700' : 'text-slate-700' }}">{{ $group['exception_class'] }}</h2>
                </div>
                <p class="mt-2 text-sm text-slate-600 bg-red-50 px-3 py-2 rounded font-mono text-xs">{{ $group['message'] }}</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-0 divide-x divide-gray-100">
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">File</div>
                    <div class="text-xs font-mono text-slate-700 truncate">{{ basename($group['file']) }}:{{ $group['line'] }}</div>
                </div>
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">Occurrences</div>
                    <div class="text-sm font-medium">{{ number_format($group['occurrence_count']) }}</div>
                </div>
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">First Seen</div>
                    <div class="text-sm text-slate-500">{{ $group['first_seen'] }}</div>
                </div>
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">Last Seen</div>
                    <div class="text-sm text-slate-500">{{ $group['last_seen'] }}</div>
                </div>
                <div class="px-5 py-3">
                    <div class="text-[10px] uppercase tracking-wider text-slate-400 mb-1">Status</div>
                    @php $statusMap = ['unresolved' => 'badge-red', 'resolved' => 'badge-green', 'ignored' => 'badge-gray'] @endphp
                    <span class="badge {{ $statusMap[$group['status']] ?? 'badge-gray' }}">{{ $group['status'] }}</span>
                </div>
            </div>
        </div>

        @if($group['file'])
            <div class="section-card">
                <div class="section-header">Location</div>
                <div class="p-4">
                    <pre class="text-xs bg-slate-800 text-slate-300 p-3 rounded-lg overflow-auto font-mono leading-relaxed">{{ $group['file'] }}:{{ $group['line'] }}</pre>
                </div>
            </div>
        @endif
    @else
        <div class="section-card"><div class="empty-state">Exception group not found.</div></div>
    @endif
</div>
@endsection
