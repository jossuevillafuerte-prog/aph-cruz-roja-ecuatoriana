<?php

namespace App\Http\Middleware;

use Closure;

class Superadmin
{
    public function handle($request, Closure $next)
    {
        abort_unless($request->user()?->role === 'superadmin', 403);

        return $next($request);
    }
}
