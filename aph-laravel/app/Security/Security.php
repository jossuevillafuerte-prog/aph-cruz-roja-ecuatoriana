<?php

namespace App\Security;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Security
{
    public static function stamp($user): string
    {
        return hash('sha256', $user->clave.':'.$user->revision);
    }

    public static function event(string $event, ?string $identity = null, ?string $target = null): void
    {
        DB::table('security_events')->insert(['identity' => $identity ?? Auth::id(), 'event' => $event, 'ip' => request()->ip(), 'created_at' => now(), 'target' => $target]);
    }

    public static function logout($request, string $reason = 'logout')
    {
        self::event($reason);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() ? response()->json(['message' => 'La sesión terminó.'], 401) : redirect('/login')->with('notice', 'La sesión terminó. Vuelve a ingresar.');
    }
}
