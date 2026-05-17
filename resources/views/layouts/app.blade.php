<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — Lookout</title>
    <link rel="stylesheet" href="{{ asset('vendor/lookout/lookout.css') }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; }
        .font-mono, code, pre { font-family: 'SF Mono', 'Fira Code', 'Fira Mono', 'Roboto Mono', Menlo, Monaco, Consolas, monospace; }
        .sidebar-link { display: flex; align-items: center; padding: 7px 12px; border-radius: 6px; font-size: 13px; color: #94a3b8; transition: all 0.15s; margin-bottom: 2px; }
        .sidebar-link:hover { color: #e2e8f0; background: rgba(255,255,255,0.06); }
        .sidebar-link.active { color: #fff; background: rgba(99,102,241,0.15); font-weight: 500; }
        .stat-card { background: #fff; border-radius: 8px; padding: 20px; border-left: 3px solid; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; line-height: 1.1; }
        .stat-card .stat-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        table th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; }
        table td { font-size: 13px; }
        table tbody tr { transition: background 0.1s; }
        table tbody tr:hover { background: #f8fafc; }
        .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; letter-spacing: 0.02em; }
        .badge-red { background: #fef2f2; color: #dc2626; }
        .badge-green { background: #f0fdf4; color: #16a34a; }
        .badge-amber { background: #fffbeb; color: #d97706; }
        .badge-blue { background: #eff6ff; color: #2563eb; }
        .badge-purple { background: #faf5ff; color: #7c3aed; }
        .badge-gray { background: #f8fafc; color: #64748b; }
        .method-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 700; letter-spacing: 0.05em; font-family: 'SF Mono', Menlo, monospace; }
        .method-GET { background: #dbeafe; color: #1d4ed8; }
        .method-POST { background: #dcfce7; color: #15803d; }
        .method-PUT { background: #fef3c7; color: #b45309; }
        .method-PATCH { background: #f3e8ff; color: #7c3aed; }
        .method-DELETE { background: #fee2e2; color: #dc2626; }
        .duration-warn { color: #d97706; font-weight: 600; }
        .duration-critical { color: #dc2626; font-weight: 600; }
        .page-title { font-size: 20px; font-weight: 700; color: #0f172a; }
        .section-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden; }
        .section-header { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; }
        .breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #64748b; margin-bottom: 20px; }
        .breadcrumb a { color: #6366f1; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .empty-state { padding: 48px 20px; text-align: center; color: #94a3b8; font-size: 14px; }
        .event-timeline-item { border-left: 2px solid #e2e8f0; padding: 0 0 16px 20px; margin-left: 8px; position: relative; }
        .event-timeline-item::before { content: ''; position: absolute; left: -5px; top: 4px; width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1; }
        .event-timeline-item.event-query::before { background: #3b82f6; }
        .event-timeline-item.event-exception::before { background: #ef4444; }
        .event-timeline-item.event-cache::before { background: #22c55e; }
        .event-timeline-item.event-outgoing_http::before { background: #8b5cf6; }
        .event-timeline-item.event-mail::before { background: #f59e0b; }
        .event-timeline-item.event-log::before { background: #94a3b8; }
        .event-timeline-item.event-notification::before { background: #ec4899; }
        .event-timeline-item.event-job_failed::before { background: #ef4444; }
        .event-timeline-item.event-job_processed::before { background: #22c55e; }
        details summary { cursor: pointer; list-style: none; }
        details summary::-webkit-details-marker { display: none; }
        details[open] summary .chevron { transform: rotate(90deg); }
        .chevron { transition: transform 0.15s; display: inline-block; }
    </style>
</head>
<body class="h-full">
    <div class="flex h-full">
        <aside class="w-56 bg-slate-900 flex flex-col flex-shrink-0 h-screen sticky top-0">
            <div class="px-4 py-4 border-b border-slate-800">
                <a href="{{ route('lookout.overview') }}" class="flex items-center gap-2 text-white font-bold text-[15px] tracking-tight">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Lookout
                </a>
            </div>
            <nav class="flex-1 px-3 py-3 space-y-0.5 overflow-y-auto">
                <a href="{{ route('lookout.overview') }}" class="sidebar-link {{ request()->routeIs('lookout.overview') ? 'active' : '' }}">Overview</a>
                <a href="{{ route('lookout.requests') }}" class="sidebar-link {{ request()->routeIs('lookout.request*') ? 'active' : '' }}">Requests</a>
                <a href="{{ route('lookout.exceptions') }}" class="sidebar-link {{ request()->routeIs('lookout.exception*') ? 'active' : '' }}">Exceptions</a>
                <a href="{{ route('lookout.queries') }}" class="sidebar-link {{ request()->routeIs('lookout.queries') ? 'active' : '' }}">Queries</a>

                <div class="pt-3 pb-1 px-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Background</div>
                <a href="{{ route('lookout.jobs') }}" class="sidebar-link {{ request()->routeIs('lookout.jobs') ? 'active' : '' }}">Jobs</a>
                <a href="{{ route('lookout.scheduled') }}" class="sidebar-link {{ request()->routeIs('lookout.scheduled') ? 'active' : '' }}">Scheduled</a>
                <a href="{{ route('lookout.commands') }}" class="sidebar-link {{ request()->routeIs('lookout.commands') ? 'active' : '' }}">Commands</a>

                <div class="pt-3 pb-1 px-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Events</div>
                <a href="{{ route('lookout.cache') }}" class="sidebar-link {{ request()->routeIs('lookout.cache') ? 'active' : '' }}">Cache</a>
                <a href="{{ route('lookout.mail') }}" class="sidebar-link {{ request()->routeIs('lookout.mail') ? 'active' : '' }}">Mail</a>
                <a href="{{ route('lookout.notifications') }}" class="sidebar-link {{ request()->routeIs('lookout.notifications') ? 'active' : '' }}">Notifications</a>
                <a href="{{ route('lookout.logs') }}" class="sidebar-link {{ request()->routeIs('lookout.logs') ? 'active' : '' }}">Logs</a>
                <a href="{{ route('lookout.outgoing') }}" class="sidebar-link {{ request()->routeIs('lookout.outgoing') ? 'active' : '' }}">Outgoing HTTP</a>

                <div class="pt-3 pb-1 px-3 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Operations</div>
                <a href="{{ route('lookout.alerts') }}" class="sidebar-link {{ request()->routeIs('lookout.alerts') ? 'active' : '' }}">Alerts</a>
                <a href="{{ route('lookout.audit') }}" class="sidebar-link {{ request()->routeIs('lookout.audit*') ? 'active' : '' }}">Audit</a>
                <a href="{{ route('lookout.health') }}" class="sidebar-link {{ request()->routeIs('lookout.health') ? 'active' : '' }}">Health</a>
            </nav>
            <div class="px-4 py-3 border-t border-slate-800 text-[11px] text-slate-500">
                Lookout v0.1.0
            </div>
        </aside>

        <main class="flex-1 overflow-auto bg-gray-50 h-screen">
            @if(session('success'))
                <div class="mx-6 mt-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg">{{ session('success') }}</div>
            @endif

            <div class="p-6 max-w-[1400px]">
                @yield('content')
            </div>
        </main>
    </div>
    <script src="{{ asset('vendor/lookout/lookout.js') }}" defer></script>
</body>
</html>
