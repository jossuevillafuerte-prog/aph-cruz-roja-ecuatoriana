<?php

namespace App\Http\Controllers;

use App\Security\Security;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function form()
    {
        return view('security.login', ['provinces' => json_decode(file_get_contents(base_path('legacy/provincias.json')), true)]);
    }

    public function login(Request $r)
    {
        $v = $this->validateOrRedirect($r, '/login', ['usuario' => 'required|string|max:40', 'password' => 'required|string|max:128']);
        $v['usuario'] = mb_strtolower(trim($v['usuario']));
        $key = 'login:'.hash('sha256', $v['usuario']);
        $ip = 'login-ip:'.hash('sha256', $r->ip());
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ip, 50)) {
            return redirect()->route('login')->withErrors(['login' => 'Demasiados intentos. Espera 5 minutos y vuelve a intentar.'])->setStatusCode(429);
        }
        $user = Auth::getProvider()->retrieveByCredentials($v);
        $valid = $user ? Auth::getProvider()->validateCredentials($user, $v) : password_verify($v['password'], '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $user || ! $valid) {
            RateLimiter::hit($key, 300);
            RateLimiter::hit($ip, 300);
            $target = $user ? 'account:'.$user->id : null;
            Security::event('login_failed', null, $target);
            if (RateLimiter::tooManyAttempts($key, 5)) {
                Security::event('login_throttled', null, $target);
            }

            return redirect()->route('login')->withErrors(['login' => 'Los datos de acceso no son correctos.']);
        }
        Auth::login($user, false);
        $r->session()->regenerate();
        $r->session()->put(['credential' => Security::stamp($user), 'authenticated_at' => time(), 'last_activity' => time()]);
        DB::table('accounts')->where('id', $user->id)->update(['last_login_at' => now()]);
        RateLimiter::clear($key);
        Security::event('login_success');

        return redirect($user->role === 'superadmin' ? '/national' : '/');
    }

    public function logout(Request $r)
    {
        return Security::logout($r);
    }

    public function status(Request $r)
    {
        return response()->json(['idle_until' => $r->session()->get('last_activity') + 600, 'absolute_until' => $r->session()->get('authenticated_at') + 28800, 'server_time' => time(), 'user' => (string) Auth::id(), 'csrf_token' => $r->session()->token()]);
    }

    public function activity(Request $r)
    {
        return $this->status($r);
    }

    public function revoke(Request $r)
    {
        if (! password_verify((string) $r->input('password'), Auth::user()->clave)) {
            return redirect('/security')->withErrors(['password' => 'Contraseña incorrecta.']);
        }
        DB::table('sessions')->where('user_id', Auth::id())->delete();
        Security::event('all_sessions_revoked');

        return Security::logout($r);
    }
}
