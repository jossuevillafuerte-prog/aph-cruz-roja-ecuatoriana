<?php

namespace App\Http\Controllers;

use App\Security\PasswordPolicy;
use App\Security\Security;
use App\Services\AccountInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivationController extends Controller
{
    public function show(string $ticket)
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $ticket), 404);

        return view('security.activate', ['ticket' => $ticket]);
    }

    public function activate(Request $r, string $ticket)
    {
        $this->validateOrRedirect($r, '/activate/'.$ticket, ['code' => 'required|digits:6', 'password' => 'required|string|confirmed|max:72']);
        $valid = DB::transaction(function () use ($r, $ticket) {
            $row = DB::table('account_invitations')->where('ticket_hash', hash('sha256', $ticket))->lockForUpdate()->first();
            if (! $row || $row->attempts >= 5 || now()->greaterThan($row->expires_at)) {
                return false;
            }
            DB::table('account_invitations')->where('id', $row->id)->increment('attempts');
            if (! hash_equals($row->code_hash, AccountInvitations::hash($r->input('code')))) {
                return false;
            }

            return $row->account_id;
        });
        if (! $valid) {
            return redirect('/activate/'.$ticket)->withErrors(['code' => 'Código inválido, vencido o con demasiados intentos. Solicita una nueva invitación.']);
        }
        if ($error = PasswordPolicy::error($r->input('password'))) {
            return redirect('/activate/'.$ticket)->withErrors(['password' => $error]);
        }
        $done = DB::transaction(function () use ($valid, $ticket, $r) {
            $invite = DB::table('account_invitations')->where('account_id', $valid)->where('ticket_hash', hash('sha256', $ticket))->lockForUpdate()->first();
            if (! $invite || now()->greaterThan($invite->expires_at)) {
                return false;
            }
            $account = DB::table('accounts')->where('id', $valid)->lockForUpdate()->first();
            if (! $account || $account->disabled_at) {
                return false;
            }
            $hash = password_hash($r->input('password'), PASSWORD_BCRYPT);
            DB::table('accounts')->where('id', $valid)->update(['password' => $hash, 'email_verified_at' => now(), 'active' => true, 'legacy_access' => false, 'revision' => DB::raw('revision+1'), 'updated_at' => now()]);
            if ($account->operator_id) {
                DB::table('operadores')->where('id', $account->operator_id)->update(['clave' => $hash, 'activo' => true]);
            }
            DB::table('account_invitations')->where('id', $invite->id)->delete();
            DB::table('sessions')->where('user_id', (string) $valid)->delete();

            return true;
        });
        if (! $done) {
            return redirect('/activate/'.$ticket)->withErrors(['code' => 'La invitación dejó de ser válida.']);
        }
        Security::event('account_activated', (string) $valid);

        return redirect('/login')->with('notice', 'Cuenta activada. Ingresa con tu usuario y contraseña.');
    }
}
