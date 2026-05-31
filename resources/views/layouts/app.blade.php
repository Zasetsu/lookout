<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $title ?? 'Dashboard') · Lookout</title>
    <link rel="stylesheet" href="{{ asset('vendor/lookout/lookout.css') }}">
</head>
@php
    use Zasetsu\Lookout\Storage\StorageContract;

    $routeName = request()->route()?->getName();
    $pageId = match (true) {
        $routeName === 'lookout.overview' => 'overview',
        str_starts_with((string) $routeName, 'lookout.request') => 'requests',
        str_starts_with((string) $routeName, 'lookout.exception') => 'exceptions',
        $routeName === 'lookout.queries' => 'queries',
        $routeName === 'lookout.outgoing' => 'outgoing',
        $routeName === 'lookout.jobs' => 'jobs',
        $routeName === 'lookout.scheduled' => 'scheduled',
        $routeName === 'lookout.commands' => 'commands',
        $routeName === 'lookout.cache' => 'cache',
        $routeName === 'lookout.mail' => 'mail',
        $routeName === 'lookout.notifications' => 'notifications',
        $routeName === 'lookout.logs' => 'logs',
        $routeName === 'lookout.alerts' => 'alerts',
        str_starts_with((string) $routeName, 'lookout.audit') => 'audit',
        $routeName === 'lookout.health' => 'health',
        default => 'overview',
    };

    $navCounts = [];
    try {
        $storage = app(StorageContract::class);
        $summary = $storage->getSummary('-24 hours');
        $statusCounts = $storage->getExceptionGroupStatusCounts();
        $failedJobs = $storage->getTotalEventsCount('job_failed');
        $alerts = $storage->getAuditLog(['action' => 'threshold_triggered'], 1, 0);

        $navCounts = [
            'requests' => $summary['total_requests'] ?? null,
            'exceptions' => $statusCounts['unresolved'] ?? null,
            'jobs' => $failedJobs > 0 ? $failedJobs : null,
            'alerts' => $alerts['total'] ?? null,
        ];
    } catch (Throwable) {
        $navCounts = [];
    }

    $nav = [
        ['group' => 'Overview', 'items' => [
            ['id' => 'overview', 'name' => 'Overview', 'icon' => 'grid', 'href' => route('lookout.overview')],
        ]],
        ['group' => 'Traffic', 'items' => [
            ['id' => 'requests', 'name' => 'Requests', 'icon' => 'activity', 'href' => route('lookout.requests'), 'count' => $navCounts['requests'] ?? null],
            ['id' => 'exceptions', 'name' => 'Exceptions', 'icon' => 'alert', 'href' => route('lookout.exceptions'), 'count' => $navCounts['exceptions'] ?? null, 'danger' => ($navCounts['exceptions'] ?? 0) > 0],
            ['id' => 'queries', 'name' => 'Queries', 'icon' => 'database', 'href' => route('lookout.queries')],
            ['id' => 'outgoing', 'name' => 'Outgoing HTTP', 'icon' => 'send', 'href' => route('lookout.outgoing')],
        ]],
        ['group' => 'Background', 'items' => [
            ['id' => 'jobs', 'name' => 'Jobs', 'icon' => 'layers', 'href' => route('lookout.jobs'), 'count' => $navCounts['jobs'] ?? null, 'danger' => ($navCounts['jobs'] ?? 0) > 0],
            ['id' => 'scheduled', 'name' => 'Scheduled', 'icon' => 'clock', 'href' => route('lookout.scheduled')],
            ['id' => 'commands', 'name' => 'Commands', 'icon' => 'terminal', 'href' => route('lookout.commands')],
        ]],
        ['group' => 'Application Events', 'items' => [
            ['id' => 'cache', 'name' => 'Cache', 'icon' => 'zap', 'href' => route('lookout.cache')],
            ['id' => 'mail', 'name' => 'Mail', 'icon' => 'mail', 'href' => route('lookout.mail')],
            ['id' => 'notifications', 'name' => 'Notifications', 'icon' => 'bell', 'href' => route('lookout.notifications')],
            ['id' => 'logs', 'name' => 'Logs', 'icon' => 'file', 'href' => route('lookout.logs')],
        ]],
        ['group' => 'Operations', 'items' => [
            ['id' => 'alerts', 'name' => 'Alerts', 'icon' => 'siren', 'href' => route('lookout.alerts'), 'count' => $navCounts['alerts'] ?? null],
            ['id' => 'audit', 'name' => 'Audit', 'icon' => 'shield', 'href' => route('lookout.audit')],
            ['id' => 'health', 'name' => 'Health', 'icon' => 'heart', 'href' => route('lookout.health')],
        ]],
    ];

    $user = request()->user();
    $userName = $user?->name ?? 'Lookout';
    $userLabel = $user?->email ?? config('app.name', 'Observability');
    $initials = collect(explode(' ', trim((string) $userName)))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => substr($part, 0, 1))
        ->implode('') ?: 'LO';
    $pageTitle = $title ?? trim($__env->yieldContent('title'));
    $pageConfig = array_merge([
        'id' => $pageId,
        'title' => $pageTitle !== '' ? $pageTitle : 'Lookout',
    ], $pageConfig ?? []);
    $meta = [
        'environment' => app()->environment(),
        'userInitials' => strtoupper($initials),
        'userName' => $userName,
        'userLabel' => $userLabel,
    ];
@endphp
<body>
    <div class="lk-shell">
        <aside class="lk-side" id="lk-sidebar"></aside>
        <div class="lk-main">
            <header class="topbar" id="lk-topbar"></header>
            <div class="content">
                <div class="wrap">
                    @if(session('success'))
                        <div class="feedback ok"><span>{{ session('success') }}</span></div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </div>
    </div>
    <script>
        window.LK_NAV = @json($nav);
        window.LK_META = @json($meta);
        window.LK_PAGE = @json($pageConfig);
    </script>
    @yield('page_script')
    <script src="{{ asset('vendor/lookout/lookout.js') }}" defer></script>
</body>
</html>
