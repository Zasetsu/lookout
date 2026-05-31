@extends('lookout::layouts.app')
@section('title', 'Exceptions')
@section('content')
@php
    $trendPoints = collect($trend ?? [])->pluck('count')->map(fn ($v) => (int) $v)->all();
    $trendTotal = array_sum($trendPoints);
    $unresolved = (int) ($statusCounts['unresolved'] ?? 0);
    $resolved = (int) ($statusCounts['resolved'] ?? 0);
    $ignored = (int) ($statusCounts['ignored'] ?? 0);
    $allTotal = $unresolved + $resolved + $ignored;
    $activeStatus = request('status', 'unresolved');
    $trendValues = implode(',', $trendPoints !== [] ? $trendPoints : [0]);
@endphp

<div class="page-title-row">
    <span class="pt">Exceptions</span>
    <span class="psub">Grouped failures · recurrence tracking · operator actions</span>
    <div class="right"><a class="btn sm" href="{{ route('lookout.exceptions') }}">Reset</a></div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><span class="k-lbl">Total groups</span><span class="k-val">{{ number_format($allTotal) }}</span><span class="k-sub">all statuses</span></div>
    <div class="kpi"><span class="k-lbl">Unresolved</span><span class="k-val {{ $unresolved > 0 ? 's-err' : 's-ok' }}">{{ number_format($unresolved) }}</span><span class="k-sub">visible by default</span></div>
    <div class="kpi"><span class="k-lbl">Occurrences</span><span class="k-val">{{ number_format($trendTotal) }}</span><span class="k-sub">last 24 hours</span></div>
    <div class="kpi"><span class="k-lbl">Resolved</span><span class="k-val s-ok">{{ number_format($resolved) }}</span><span class="k-sub">{{ number_format($ignored) }} ignored</span></div>
</div>

<div class="panel mb12">
    <div class="panel-h"><h3>Exception trend</h3><span class="sub">events by hour · last 24h</span></div>
    <div class="panel-b"><div class="js-bars" data-tipunit="events" data-values="{{ $trendValues }}" data-x="oldest|now" data-err=""></div></div>
</div>

<form method="GET" action="{{ route('lookout.exceptions') }}" class="filters">
    <div class="seg-toggle">
        @foreach(['unresolved' => 'Unresolved', 'resolved' => 'Resolved', 'ignored' => 'Ignored'] as $value => $label)
            <button type="submit" name="status" value="{{ $value }}" class="{{ $activeStatus === $value ? 'on' : '' }}">{{ $label }}</button>
        @endforeach
    </div>
    <div class="field"><label>Class</label><input name="class" value="{{ request('class') }}" placeholder="exception class"></div>
    <button class="btn primary" type="submit">Apply</button>
    <span class="result-meta" data-total="{{ $total }}">{{ number_format(count($groups)) }} of {{ number_format($total) }} shown</span>
</form>

<div class="table-wrap">
    <div class="table-scroll">
        <table class="lk" data-filterable>
            <thead><tr><th>Exception</th><th>File</th><th class="num">Occurrences</th><th>Status</th><th>Last seen</th><th class="num">Actions</th></tr></thead>
            <tbody>
            @forelse($groups as $group)
                @php
                    $status = $group['status'] ?? 'unresolved';
                    $tone = $status === 'unresolved' ? 'err' : ($status === 'resolved' ? 'ok' : 'neu');
                @endphp
                <tr class="row" data-state="{{ $status }}" data-class="{{ strtolower($group['exception_class'] ?? '') }}" onclick="location.href='{{ route('lookout.exception-detail', $group['id']) }}'">
                    <td><div class="stack"><span class="route mono truncate">{{ $group['exception_class'] }}</span><span class="sm truncate">{{ $group['message'] }}</span></div></td>
                    <td class="mono subtle wrap-anywhere">{{ basename($group['file'] ?? '') }}:{{ $group['line'] ?? '' }}</td>
                    <td class="num"><span class="badge {{ ($group['occurrence_count'] ?? 0) > 5 ? 'err' : 'warn' }}">{{ number_format($group['occurrence_count'] ?? 0) }}</span></td>
                    <td><span class="badge {{ $tone }}">{{ $status }}</span></td>
                    <td class="t-time">{{ $group['last_seen'] ?? '' }}</td>
                    <td class="num" onclick="event.stopPropagation()">
                        @if($status === 'unresolved')
                            <div class="flex gap6" style="justify-content:flex-end">
                                <form method="POST" action="{{ route('lookout.exception-resolve', $group['id']) }}">@csrf<button class="btn sm primary" type="submit">Resolve</button></form>
                                <form method="POST" action="{{ route('lookout.exception-ignore', $group['id']) }}">@csrf<button class="btn sm" type="submit">Ignore</button></form>
                            </div>
                        @else
                            <span class="subtle">No action</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><h4>No exception groups</h4><p>No groups match the current filters.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
