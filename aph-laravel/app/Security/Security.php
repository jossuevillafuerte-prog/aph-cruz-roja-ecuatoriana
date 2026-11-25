<?php

namespace App\Security;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Security
{
    public const UNSAVED_NOTICE = 'La sesión terminó y los cambios enviados no se guardaron. Vuelve a ingresar.';

    public static function stamp($user): string
    {
        return hash('sha256', $user->clave.':'.$user->revision);
    }

    public static function event(string $event, ?string $identity = null, ?string $target = null, ?string $reference = null): void
    {
        DB::table('security_events')->insert(['identity' => $identity ?? Auth::id(), 'event' => $event, 'ip' => request()->ip(), 'created_at' => now(), 'target' => $target, 'reference' => $reference]);
    }

    public static function logout($request, string $reason = 'logout')
    {
        self::event($reason);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'La sesión terminó.'], 401);
        }
        $changesWereLost = ! $request->isMethodSafe() && in_array($reason, ['credential_revoked', 'absolute_timeout', 'idle_timeout'], true);

        return redirect('/login')->with('notice', $changesWereLost ? self::UNSAVED_NOTICE : 'La sesión terminó. Vuelve a ingresar.');
    }
}
