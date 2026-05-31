@extends('lookout::layouts.app')
@section('title', 'Logs')
@section('content')
@php
    use Zasetsu\Lookout\Http\Support\Payload;
    $levels = collect($logs)->map(fn ($log) => Payload::string(Payload::decode($log['payload'] ?? null), 'level', 'info'));
@endphp

<div class="page-title-row"><span class="pt">Logs</span><span class="psub">Application log events captured inside traces</span></div>

<div class="kpi-row" style="grid-template-columns:repeat(6,1fr)">
    <div class="kpi"><span class="k-lbl">Total entries</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">stored log events</span></div>
    @foreach(['debug', 'info', 'notice', 'warning', 'error'] as $level)
        <div class="kpi"><span class="k-lbl">{{ ucfirst($level) }}</span><span class="k-val {{ in_array($level, ['warning', 'error'], true) ? ($level === 'error' ? 's-err' : 's-warn') : '' }}">{{ number_format($levels->filter(fn ($l) => $l === $level)->count()) }}</span><span class="k-sub">visible entries</span></div>
    @endforeach
</div>

<div class="filters">
    <div class="seg-toggle" data-filter-group="level"><button class="on" data-v="all">All</button><button data-v="info">Info</button><button data-v="warning">Warning</button><button data-v="error">Error</button></div>
    <div class="field"><label>Channel</label><input data-filter="channel" data-match="contains" placeholder="channel"></div>
    <div class="field"><label>Message</label><input data-filter="msg" data-match="contains" placeholder="search text"></div>
    <span class="result-meta" data-total="{{ count($logs) }}">{{ number_format(count($logs)) }} shown</span>
</div>

<div class="table-wrap"><div class="table-scroll"><table class="lk" data-filterable>
    <thead><tr><th>Level</th><th>Message</th><th>Channel</th><th>Context</th><th>Time</th></tr></thead>
    <tbody>
    @forelse($logs as $log)
        @php
            $p = Payload::decode($log['payload'] ?? null);
            $level = Payload::string($p, 'level', 'info');
            $message = Payload::string($p, 'message', $log['labels'] ?? '');
            $channel = Payload::string($p, 'channel', 'default');
            $tone = match ($level) { 'error', 'critical', 'alert', 'emergency' => 'err', 'warning' => 'warn', 'debug' => 'neu', default => 'info' };
        @endphp
        <tr class="row" data-level="{{ $level }}" data-channel="{{ strtolower($channel) }}" data-msg="{{ strtolower($message) }}" data-expand>
            <td><span class="badge {{ $tone }}">{{ $level }}</span></td>
            <td><span class="route truncate">{{ $message }}</span></td>
            <td><span class="badge neu">{{ $channel }}</span></td>
            <td class="subtle">{{ array_key_exists('context', $p) ? 'available' : 'empty' }}</td>
            <td class="t-time">{{ $log['timestamp'] ?? '' }}</td>
        </tr>
        <tr class="detail-row"><td colspan="5"><div class="detail-inner"><pre class="code">{{ json_encode($p['context'] ?? $p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></td></tr>
    @empty
        <tr><td colspan="5"><div class="empty"><h4>No logs</h4><p>Log events will appear here when application logs are emitted inside sampled traces.</p></div></td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
