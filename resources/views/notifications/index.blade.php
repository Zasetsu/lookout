@extends('lookout::layouts.app')
@section('title', 'Notifications')
@section('content')
@php use Zasetsu\Lookout\Http\Support\Payload; @endphp

<div class="page-title-row"><span class="pt">Notifications</span><span class="psub">Laravel notification delivery events</span></div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Total sent</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">stored notifications</span></div>
    <div class="kpi"><span class="k-lbl">Unique types</span><span class="k-val">{{ number_format($uniqueTypes) }}</span><span class="k-sub">visible classes</span></div>
    <div class="kpi"><span class="k-lbl">Unique channels</span><span class="k-val">{{ number_format($uniqueChannels) }}</span><span class="k-sub">visible channels</span></div>
    <div class="kpi"><span class="k-lbl">Visible</span><span class="k-val">{{ number_format(count($notifications)) }}</span><span class="k-sub">latest entries</span></div>
</div>

<div class="filters">
    <div class="field"><label>Channel</label><input data-filter="channel" data-match="contains" placeholder="mail, database"></div>
    <div class="field"><label>Class</label><input data-filter="class" data-match="contains" placeholder="notification class"></div>
    <span class="result-meta" data-total="{{ count($notifications) }}">{{ number_format(count($notifications)) }} shown</span>
</div>

<div class="table-wrap"><div class="table-scroll"><table class="lk" data-filterable>
    <thead><tr><th>Notification</th><th>Channel</th><th>Notifiable</th><th>Time</th></tr></thead>
    <tbody>
    @forelse($notifications as $notification)
        @php
            $p = Payload::decode($notification['payload'] ?? null);
            $class = Payload::string($p, 'notification', $notification['labels'] ?? 'Notification');
            $channel = Payload::string($p, 'channel', 'unknown');
            $notifiable = Payload::string($p, 'notifiable', 'n/a');
        @endphp
        <tr class="row" data-channel="{{ strtolower($channel) }}" data-class="{{ strtolower($class) }}">
            <td><div class="stack"><span class="route mono truncate">{{ class_basename($class) }}</span><span class="sm truncate">{{ $class }}</span></div></td>
            <td><span class="badge info">{{ $channel }}</span></td>
            <td class="mono subtle truncate">{{ $notifiable }}</td>
            <td class="t-time">{{ $notification['timestamp'] ?? '' }}</td>
        </tr>
    @empty
        <tr><td colspan="4"><div class="empty"><h4>No notifications</h4><p>Notification events will appear here when Laravel sends them.</p></div></td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
