<?php

namespace Zasetsu\Lookout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('lookout.dashboard.allowed_ips', []);

        if (empty($allowedIps)) {
            return $next($request);
        }

        $clientIp = $request->ip();

        if (in_array($clientIp, $allowedIps, true)) {
            return $next($request);
        }

        abort(403, 'Access denied.');
    }
}
