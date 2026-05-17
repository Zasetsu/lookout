<?php

namespace Zasetsu\Lookout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = config('lookout.dashboard.basic_auth.user');
        $pass = config('lookout.dashboard.basic_auth.pass');

        if (empty($user) || empty($pass)) {
            return $next($request);
        }

        $providedUser = $request->getUser();
        $providedPass = $request->getPassword();

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
