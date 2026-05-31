<?php

namespace Zasetsu\Lookout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocalhostOnly
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('lookout.dashboard.localhost_only', false)) {
            return $next($request);
        }

        $ip = $request->ip();

        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return $next($request);
        }

        abort(403, 'Access denied. Localhost only.');
    }
}
