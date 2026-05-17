@extends('lookout::layouts.app')
@section('title', 'Alerts')
@section('content')
<div class="space-y-6">
    <div>
        <h1 class="page-title">Alerts</h1>
        <p class="text-sm text-slate-500 mt-1">{{ number_format($total ?? 0) }} threshold dispatches recorded in the audit log</p>
    </div>

    <div class="section-card">
        @if(!empty($entries))
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left px-5 py-3">Time</th>
                        <th class="text-left px-5 py-3">Threshold</th>
                        <th class="text-left px-5 py-3">Condition</th>
                        <th class="text-left px-5 py-3">Deliveries</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($entries as $entry)
                        @php
                            $details = \Zasetsu\Lookout\Http\Support\Payload::decode($entry['details'] ?? null);
                            $deliveries = is_array($details['deliveries'] ?? null) ? $details['deliveries'] : [];
                            $metric = \Zasetsu\Lookout\Http\Support\Payload::string($details, 'metric', 'unknown');
                            $condition = \Zasetsu\Lookout\Http\Support\Payload::string($details, 'condition', '?');
                            $value = \Zasetsu\Lookout\Http\Support\Payload::string($details, 'value', '?');
                        @endphp
                        <tr>
                            <td class="px-5 py-3 text-xs text-slate-500 font-mono whitespace-nowrap">{{ $entry['created_at'] ?? '-' }}</td>
                            <td class="px-5 py-3">
                                <div class="text-sm font-medium text-slate-900">{{ \Zasetsu\Lookout\Http\Support\Payload::string($details, 'name', 'Threshold') }}</div>
                                <div class="text-[11px] text-slate-400">#{{ \Zasetsu\Lookout\Http\Support\Payload::string($details, 'threshold_id', '-') }} / {{ $metric }}</div>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-600 font-mono">{{ $condition }} {{ $value }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($deliveries as $delivery)
                                        @php
                                            $status = is_array($delivery) ? (string) ($delivery['status'] ?? 'unknown') : 'unknown';
                                            $channel = is_array($delivery) ? (string) ($delivery['channel'] ?? 'unknown') : 'unknown';
                                            $badge = $status === 'sent' ? 'badge-green' : ($status === 'failed' ? 'badge-red' : 'badge-gray');
                                        @endphp
                                        <span class="badge {{ $badge }}">{{ $channel }}: {{ $status }}</span>
                                    @empty
                                        <span class="badge badge-gray">No channels</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-state">No alert dispatches recorded yet</div>
        @endif
    </div>
</div>
@endsection
