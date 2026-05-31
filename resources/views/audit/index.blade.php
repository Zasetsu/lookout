@extends('lookout::layouts.app')
@section('title', 'Audit')
@section('content')

<div class="page-title-row">
    <span class="pt">Audit Log</span>
    <span class="psub">Dashboard mutations, prune runs, and alert trigger records</span>
    <div class="right">
        <a class="btn sm" href="{{ route('lookout.audit-export', ['format' => 'csv']) }}">Export CSV</a>
        <a class="btn sm" href="{{ route('lookout.audit-export', ['format' => 'json']) }}">Export JSON</a>
    </div>
</div>

<form method="GET" action="{{ route('lookout.audit') }}" class="filters">
    <div class="field"><label>Action</label><input name="action" value="{{ $filters['action'] ?? '' }}" placeholder="threshold_triggered"></div>
    <div class="field"><label>Since</label><input name="since" value="{{ $filters['since'] ?? '' }}" placeholder="24h or 2026-05-17"></div>
    <button class="btn primary" type="submit">Filter</button>
    <a class="btn" href="{{ route('lookout.audit') }}">Reset</a>
    <span class="result-meta" data-total="{{ $total }}">{{ number_format(count($entries)) }} of {{ number_format($total) }} shown</span>
</form>

<div class="table-wrap"><div class="table-scroll"><table class="lk">
    <thead><tr><th>Created</th><th>Action</th><th>User</th><th>IP</th><th>Details</th></tr></thead>
    <tbody>
    @forelse($entries as $entry)
        @php
            $details = is_array($entry['details'] ?? null) ? $entry['details'] : (is_string($entry['details'] ?? null) ? json_decode($entry['details'], true) : []);
            $details = is_array($details) ? $details : [];
        @endphp
        <tr class="row" data-expand>
            <td class="t-time">{{ $entry['created_at'] ?? '' }}</td>
            <td><span class="badge info">{{ $entry['action'] ?? '' }}</span></td>
            <td class="mono subtle">{{ $entry['user_id'] ?? 'system' }}</td>
            <td class="mono subtle">{{ $entry['ip'] ?? 'n/a' }}</td>
            <td><span class="subtle">{{ $details === [] ? 'empty' : 'available' }}</span></td>
        </tr>
        <tr class="detail-row"><td colspan="5"><div class="detail-inner"><pre class="code">{{ json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div></td></tr>
    @empty
        <tr><td colspan="5"><div class="empty"><h4>No audit entries</h4><p>State-changing dashboard actions and alert triggers will appear here.</p></div></td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
