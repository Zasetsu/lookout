@extends('lookout::layouts.app')
@section('title', 'Mail')
@section('content')
@php use Zasetsu\Lookout\Http\Support\Payload; @endphp

<div class="page-title-row"><span class="pt">Mail</span><span class="psub">Sent mail events and recipients</span></div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Total sent</span><span class="k-val">{{ number_format($total) }}</span><span class="k-sub">stored mail events</span></div>
    <div class="kpi"><span class="k-lbl">Unique subjects</span><span class="k-val">{{ number_format($uniqueSubjects) }}</span><span class="k-sub">visible events</span></div>
    <div class="kpi"><span class="k-lbl">Unique recipients</span><span class="k-val">{{ number_format($uniqueRecipients) }}</span><span class="k-sub">visible recipients</span></div>
    <div class="kpi"><span class="k-lbl">Visible</span><span class="k-val">{{ number_format(count($mails)) }}</span><span class="k-sub">latest entries</span></div>
</div>

<div class="filters">
    <div class="field"><label>Subject</label><input data-filter="subject" data-match="contains" placeholder="subject text"></div>
    <div class="field"><label>Recipient</label><input data-filter="to" data-match="contains" placeholder="email"></div>
    <span class="result-meta" data-total="{{ count($mails) }}">{{ number_format(count($mails)) }} shown</span>
</div>

<div class="table-wrap"><div class="table-scroll"><table class="lk" data-filterable>
    <thead><tr><th>Subject</th><th>To</th><th>From</th><th>Time</th></tr></thead>
    <tbody>
    @forelse($mails as $mail)
        @php
            $p = Payload::decode($mail['payload'] ?? null);
            $subject = Payload::string($p, 'subject', $mail['labels'] ?? 'Mail');
            $to = implode(', ', Payload::stringList($p, 'to'));
            $from = implode(', ', Payload::stringList($p, 'from'));
        @endphp
        <tr class="row" data-subject="{{ strtolower($subject) }}" data-to="{{ strtolower($to) }}">
            <td><span class="route truncate">{{ $subject }}</span></td>
            <td class="mono subtle truncate">{{ $to !== '' ? $to : 'n/a' }}</td>
            <td class="mono subtle truncate">{{ $from !== '' ? $from : 'n/a' }}</td>
            <td class="t-time">{{ $mail['timestamp'] ?? '' }}</td>
        </tr>
    @empty
        <tr><td colspan="4"><div class="empty"><h4>No mail events</h4><p>Mail events will appear here after Laravel sends messages.</p></div></td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
