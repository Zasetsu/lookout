<?php

namespace Zasetsu\Lookout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class Authorize
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('lookout.dashboard.enabled', false)) {
            abort(404);
        }

        if (Gate::has('viewLookout')) {
            if (Gate::allows('viewLookout')) {
                return $next($request);
            }

            abort(404);
        }

        if ($this->hasConfiguredAuthLayer()) {
            return $next($request);
        }

        abort(404);
    }

    protected function hasConfiguredAuthLayer(): bool
    {
        $user = config('lookout.dashboard.basic_auth.user');
        $pass = config('lookout.dashboard.basic_auth.pass');

        if (! empty($user) && ! empty($pass)) {
            return true;
        }

        if (! empty(config('lookout.dashboard.allowed_ips'))) {
            return true;
        }

        if (config('lookout.dashboard.localhost_only', false)) {
            return true;
        }

        return false;
    }
}
