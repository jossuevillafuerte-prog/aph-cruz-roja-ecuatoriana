<?php

namespace App\Services;

use App\Rules\InstitutionalEmail;
use App\Security\Security;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AccountInvitations
{
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    public function send($r, int $id)
    {
        $account = DB::table('accounts')->find($id);
        abort_unless($account, 404);
        if (! config('institution.invitations_enabled')) {
            return redirect()->route('users.index')->with('notice', 'Envío pendiente: falta configurar el servicio institucional de correo y la URL pública. No se envió ningún código.');
        }
        if ($account->disabled_at) {
            return redirect()->route('users.index')->withErrors(['email' => 'La cuenta está suspendida. No se enviará una activación.']);
        }
        if (! $account->email) {
            return redirect()->route('users.index')->withErrors(['email' => 'Completa el correo institucional antes de enviar.']);
        }
        if ($account->email_verified_at) {
            return redirect()->route('users.index')->withErrors(['email' => 'El correo ya está verificado. No necesita activación.']);
        }
        $validator = validator(['email' => $account->email], ['email' => [new InstitutionalEmail]]);
        if ($validator->fails()) {
            return redirect()->route('users.index')->withErrors($validator);
        }
        $minute = 'invite-minute:'.$id;
        $hour = 'invite-hour:'.$id;
        if (RateLimiter::tooManyAttempts($minute, 1) || RateLimiter::tooManyAttempts($hour, 5)) {
            return redirect()->route('users.index')->withErrors(['email' => 'Espera antes de reenviar la invitación.']);
        }
        RateLimiter::hit($minute, 60);
        RateLimiter::hit($hour, 3600);
        $ticket = bin2hex(random_bytes(32));
        $code = (string) random_int(100000, 999999);
        $ticketHash = hash('sha256', $ticket);
        DB::table('account_invitations')->updateOrInsert(['account_id' => $id], ['ticket_hash' => $ticketHash, 'code_hash' => self::hash($code), 'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'sent_at' => now()]);
        try {
            Mail::raw('Activa tu cuenta APH en '.url('/activate/'.$ticket)."\nCódigo: ".$code."\nVence en 10 minutos. No compartas este código.", fn ($m) => $m->to($account->email)->subject('Activación de cuenta APH'));
        } catch (\Throwable) {
            DB::table('account_invitations')->where('ticket_hash', $ticketHash)->delete();
            Security::event('invitation_failed', null, 'account:'.$id);

            return redirect()->route('users.index')->withErrors(['email' => 'No se pudo enviar. Revisa la configuración de correo e inténtalo nuevamente.']);
        }
        Security::event('invitation_sent', null, 'account:'.$id);

        return redirect()->route('users.index')->with('notice', 'Invitación enviada. El código anterior dejó de ser válido.');
    }
}
