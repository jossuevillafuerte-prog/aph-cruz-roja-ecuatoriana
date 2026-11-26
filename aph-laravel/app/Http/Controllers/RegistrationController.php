<?php

namespace App\Http\Controllers;

use App\Rules\InstitutionalEmail;
use App\Security\Security;
use App\Services\AccountInvitations;
use App\Services\PendingAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Solicitudes públicas de cuenta que el superadministrador aprueba o rechaza en Usuarios.
 */
class RegistrationController extends Controller
{
    public const SENT_NOTICE = 'Solicitud enviada. El superadministrador la revisará; si la aprueba, recibirás en tu correo institucional el enlace para activar la cuenta.';

    public function form()
    {
        return view('security.register', ['provinces' => $this->provinces()]);
    }

    public function store(Request $r)
    {
        $r->merge(['username' => mb_strtolower(trim((string) $r->input('username'))), 'email' => mb_strtolower(trim((string) $r->input('email')))]);
        $provinces = $this->provinces();
        $v = $this->validateOrRedirect($r, '/register', [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'username' => ['required', 'regex:/\A[a-z0-9][a-z0-9._-]{2,39}\z/'],
            'email' => ['required', 'max:254', new InstitutionalEmail, function ($attribute, $value, $fail) use ($r) {
                $domain = substr(strrchr((string) $value, '@'), 1);
                $provinceDomain = config('institution.domains.'.$r->input('province'));
                if ($provinceDomain && ! in_array($domain, [$provinceDomain, config('institution.national_domain')], true)) {
                    $fail('El correo debe ser de tu provincia (@'.$provinceDomain.') o nacional (@'.config('institution.national_domain').').');
                }
            }],
            'province' => ['required', Rule::in(array_keys($provinces))],
        ]);
        $v['first_name'] = trim($v['first_name']);
        $v['last_name'] = trim($v['last_name']);

        // La respuesta es la misma si el usuario o el correo ya existen, para no revelar cuentas.
        if (! $this->isTaken($v['username'], $v['email'])) {
            $id = DB::table('registration_requests')->insertGetId($v + ['status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            Security::event('registration_requested', null, 'registration:'.$id);
            $this->notifySuperadmins($id, $v, $provinces[$v['province']]['nombre']);
        }

        return redirect()->route('login')->with('notice', self::SENT_NOTICE);
    }

    public function approve(Request $r, int $id)
    {
        $result = DB::transaction(function () use ($r, $id) {
            $request = DB::table('registration_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($request, 404);
            if ($request->status !== 'pending') {
                return 'Esta solicitud ya fue revisada.';
            }
            if (DB::table('accounts')->where('username', $request->username)->orWhere('email', $request->email)->exists()) {
                $this->review($id, 'rejected', $r);

                return 'Ya existe una cuenta con ese usuario o correo. La solicitud se marcó como rechazada.';
            }
            $this->review($id, 'approved', $r);

            return PendingAccounts::create(['username' => $request->username, 'name' => $request->first_name.' '.$request->last_name, 'first_name' => $request->first_name, 'last_name' => $request->last_name, 'email' => $request->email, 'province' => $request->province]);
        });
        if (is_string($result)) {
            return redirect()->route('users.index')->withErrors(['registration' => $result]);
        }
        Security::event('registration_approved', null, 'account:'.$result, 'registration:'.$id);
        Security::event('account_created', null, 'account:'.$result);

        return app(AccountInvitations::class)->send($r, $result);
    }

    public function reject(Request $r, int $id)
    {
        $updated = DB::table('registration_requests')->where('id', $id)->where('status', 'pending')->update(['status' => 'rejected', 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
        if (! $updated) {
            return redirect()->route('users.index')->withErrors(['registration' => 'Esta solicitud ya fue revisada.']);
        }
        Security::event('registration_rejected', null, 'registration:'.$id);

        return redirect()->route('users.index')->with('notice', 'Solicitud rechazada. No se creó ninguna cuenta.');
    }

    private function review(int $id, string $status, Request $r): void
    {
        DB::table('registration_requests')->where('id', $id)->update(['status' => $status, 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
    }

    private function isTaken(string $username, string $email): bool
    {
        $match = fn ($q) => $q->where('username', $username)->orWhere('email', $email);

        return DB::table('accounts')->where($match)->exists()
            || DB::table('registration_requests')->where('status', 'pending')->where($match)->exists();
    }

    /**
     * @param  array{first_name: string, last_name: string, username: string, email: string, province: string}  $values
     */
    private function notifySuperadmins(int $id, array $values, string $provinceName): void
    {
        if (! config('institution.invitations_enabled')) {
            return;
        }
        $recipients = DB::table('accounts')->where('role', 'superadmin')->where('active', true)->whereNull('disabled_at')->whereNotNull('email')->pluck('email')->all();
        if (! $recipients) {
            return;
        }
        $body = "Nueva solicitud de cuenta en Registro APH.\n\n"
            .'Nombre: '.$values['first_name'].' '.$values['last_name']."\n"
            .'Usuario: '.$values['username']."\n"
            .'Correo: '.$values['email']."\n"
            .'Provincia: '.$provinceName."\n\n"
            .'Revísala y apruébala o recházala en '.url('/users#solicitudes')."\n";
        try {
            Mail::raw($body, fn ($m) => $m->to($recipients)->subject('Solicitud de cuenta APH: '.$values['first_name'].' '.$values['last_name']));
        } catch (\Throwable) {
            // La solicitud ya quedó guardada y visible en Usuarios aunque el correo falle.
            Security::event('registration_notice_failed', null, 'registration:'.$id);
        }
    }

    private function provinces(): array
    {
        return json_decode(file_get_contents(base_path('legacy/provincias.json')), true);
    }
}
