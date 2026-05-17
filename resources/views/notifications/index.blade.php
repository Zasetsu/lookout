@extends('lookout::layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="space-y-6">
    <h1 class="page-title">Notifications</h1>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="stat-card" style="border-left-color: #ec4899">
            <div class="stat-label">Total Sent</div>
            <div class="stat-value text-slate-900">{{ number_format($total) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #8b5cf6">
            <div class="stat-label">Unique Types</div>
            <div class="stat-value text-slate-900">{{ number_format($uniqueTypes ?? 0) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Channels Used</div>
            <div class="stat-value text-slate-900">{{ number_format($uniqueChannels ?? 0) }}</div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Recent Notifications</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ number_format($total) }} sent</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Notification</th>
                    <th class="text-left px-5 py-3">Channel</th>
                    <th class="text-left px-5 py-3">Notifiable</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($notifications as $notification)
                    @php $p = \Zasetsu\Lookout\Http\Support\Payload::decode($notification['payload'] ?? null) @endphp
                    <tr>
                        <td class="px-5 py-3 text-sm font-medium">{{ class_basename($p['notification'] ?? 'Unknown') }}</td>
                        <td class="px-5 py-3">
                            <span class="badge badge-purple">{{ $p['channel'] ?? '&mdash;' }}</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $p['notifiable'] ?? '&mdash;' }}</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $notification['timestamp'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($notifications))
            <div class="empty-state">No notifications recorded yet.</div>
        @endif
    </div>
</div>
@endsection
