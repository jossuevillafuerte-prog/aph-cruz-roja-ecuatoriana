<?php

namespace App\Http\Middleware;

use Closure;

class SecureResponse
{
    public function handle($request, Closure $next)
    {
        if (app()->environment('production') && (! $request->isSecure() || ! config('session.secure'))) {
            abort(403, 'El acceso requiere HTTPS y cookies seguras.');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
