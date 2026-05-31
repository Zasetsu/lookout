@extends('lookout::layouts.app')
@section('title', 'Exception Detail')
@php
    $pageConfig = [
        'id' => 'exceptions',
        'title' => 'Exception Detail',
        'crumbs' => [
            ['label' => 'Exceptions', 'href' => route('lookout.exceptions')],
            ['label' => class_basename($group['exception_class'] ?? 'Exception')],
        ],
    ];
@endphp
@section('content')
@php
    $status = $group['status'] ?? 'unresolved';
    $tone = $status === 'unresolved' ? 'err' : ($status === 'resolved' ? 'ok' : 'neu');
@endphp

<div class="page-title-row">
    <span class="pt">{{ class_basename($group['exception_class'] ?? 'Exception') }}</span>
    <span class="psub mono">{{ $group['fingerprint'] ?? '' }}</span>
    <div class="right">
        @if(($group['status'] ?? 'unresolved') === 'unresolved')
            <form method="POST" action="{{ route('lookout.exception-resolve', $group['id']) }}">@csrf<button class="btn primary" type="submit">Resolve</button></form>
            <form method="POST" action="{{ route('lookout.exception-ignore', $group['id']) }}">@csrf<button class="btn" type="submit">Ignore</button></form>
        @endif
    </div>
</div>

<div class="kpi-row" style="grid-template-columns:repeat(5,1fr)">
    <div class="kpi"><span class="k-lbl">Status</span><span class="k-val {{ $status === 'unresolved' ? 's-err' : 's-ok' }}">{{ $status }}</span><span class="k-sub">group lifecycle</span></div>
    <div class="kpi"><span class="k-lbl">Occurrences</span><span class="k-val">{{ number_format($group['occurrence_count'] ?? 0) }}</span><span class="k-sub">all time</span></div>
    <div class="kpi"><span class="k-lbl">First seen</span><span class="k-val" style="font-size:15px">{{ $group['first_seen'] ?? 'n/a' }}</span><span class="k-sub">created at</span></div>
    <div class="kpi"><span class="k-lbl">Last seen</span><span class="k-val" style="font-size:15px">{{ $group['last_seen'] ?? 'n/a' }}</span><span class="k-sub">latest recurrence</span></div>
    <div class="kpi"><span class="k-lbl">Line</span><span class="k-val">{{ $group['line'] ?? 'n/a' }}</span><span class="k-sub">{{ basename($group['file'] ?? '') }}</span></div>
</div>

<div class="grid split" style="grid-template-columns:1fr 1fr">
    <div class="panel">
        <div class="panel-h"><h3>Exception group</h3><span class="badge {{ $tone }}">{{ $status }}</span></div>
        <div class="panel-b">
            <dl class="def-list">
                <dt>Class</dt><dd class="wrap-anywhere">{{ $group['exception_class'] ?? 'n/a' }}</dd>
                <dt>Message</dt><dd class="wrap-anywhere">{{ $group['message'] ?? 'n/a' }}</dd>
                <dt>File</dt><dd class="wrap-anywhere">{{ $group['file'] ?? 'n/a' }}</dd>
                <dt>Line</dt><dd>{{ $group['line'] ?? 'n/a' }}</dd>
                <dt>Resolved at</dt><dd>{{ $group['resolved_at'] ?? 'not resolved' }}</dd>
            </dl>
        </div>
    </div>
    <div class="panel">
        <div class="panel-h"><h3>Operational context</h3></div>
        <div class="panel-b">
            <dl class="def-list">
                <dt>Fingerprint</dt><dd class="wrap-anywhere">{{ $group['fingerprint'] ?? 'n/a' }}</dd>
                <dt>Created</dt><dd>{{ $group['created_at'] ?? 'n/a' }}</dd>
                <dt>Updated</dt><dd>{{ $group['updated_at'] ?? 'n/a' }}</dd>
                <dt>Dashboard action</dt><dd>{{ $status === 'unresolved' ? 'Resolve or ignore this group' : 'No active action' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
