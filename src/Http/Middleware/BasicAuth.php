<?php

namespace Zasetsu\Lookout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BasicAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = config('lookout.dashboard.basic_auth.user');
        $pass = config('lookout.dashboard.basic_auth.pass');

        if (empty($user) || empty($pass)) {
            return $next($request);
        }

        $providedUser = $request->headers->get('PHP_AUTH_USER');
        $providedPass = $request->headers->get('PHP_AUTH_PW');

        if (
            is_string($providedUser)
            && is_string($providedPass)
            && hash_equals($user, $providedUser)
            && hash_equals($pass, $providedPass)
        ) {
            return $next($request);
        }

        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Lookout Dashboard"',
        ]);
    }
}
