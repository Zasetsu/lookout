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

        if ($providedUser === $user && $providedPass === $pass) {
            return $next($request);
        }

        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Lookout Dashboard"',
        ]);
    }
}
