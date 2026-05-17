@extends('lookout::layouts.app')
@section('title', 'Mail')
@section('content')
<div class="space-y-6">
    <h1 class="page-title">Mail</h1>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="stat-card" style="border-left-color: #f59e0b">
            <div class="stat-label">Total Sent</div>
            <div class="stat-value text-slate-900">{{ number_format($total) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #6366f1">
            <div class="stat-label">Unique Subjects</div>
            <div class="stat-value text-slate-900">{{ number_format($uniqueSubjects ?? 0) }}</div>
        </div>
        <div class="stat-card" style="border-left-color: #22c55e">
            <div class="stat-label">Unique Recipients</div>
            <div class="stat-value text-slate-900">{{ number_format($uniqueRecipients ?? 0) }}</div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header flex items-center justify-between">
            <span>Recent Mail</span>
            <span class="text-[11px] font-normal normal-case tracking-normal text-slate-400">{{ number_format($total) }} sent</span>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-5 py-3">Subject</th>
                    <th class="text-left px-5 py-3">To</th>
                    <th class="text-left px-5 py-3">From</th>
                    <th class="text-right px-5 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($mails as $mail)
                    @php $p = \Zasetsu\Lookout\Http\Support\Payload::decode($mail['payload'] ?? null) @endphp
                    <tr>
                        <td class="px-5 py-3 text-sm font-medium">{{ \Zasetsu\Lookout\Http\Support\Payload::string($p, 'subject', '(No Subject)') }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ implode(', ', \Zasetsu\Lookout\Http\Support\Payload::stringList($p, 'to')) }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ implode(', ', \Zasetsu\Lookout\Http\Support\Payload::stringList($p, 'from')) }}</td>
                        <td class="px-5 py-3 text-right text-xs text-slate-400">{{ $mail['timestamp'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($mails))
            <div class="empty-state">No mail recorded yet.</div>
        @endif
    </div>
</div>
@endsection
