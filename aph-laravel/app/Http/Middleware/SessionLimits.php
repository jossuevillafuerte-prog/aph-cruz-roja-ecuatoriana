<?php

namespace App\Http\Middleware;

use App\Security\Security;
use Closure;
use Illuminate\Support\Facades\Auth;

class SessionLimits
{
    public function handle($request, Closure $next)
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Inicia sesión.'], 401);
            }

            return $request->isMethodSafe() ? redirect('/login') : redirect('/login')->with('notice', Security::UNSAVED_NOTICE);
        }
        $s = $request->session();
        $now = time();
        if (! hash_equals(Security::stamp(Auth::user()), (string) $s->get('credential', ''))) {
            return Security::logout($request, 'credential_revoked');
        }
        if ($now - (int) $s->get('authenticated_at', 0) >= 28800) {
            return Security::logout($request, 'absolute_timeout');
        }
        if ($now - (int) $s->get('last_activity', 0) >= 600) {
            return Security::logout($request, 'idle_timeout');
        }
        // Only explicit foreground interaction or state-changing requests renew activity.
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && ! $request->is('session/status', 'drafts', 'drafts/*')) {
            $s->put('last_activity', $now);
        }

        return $next($request);
    }
}
