<?php

namespace App\Http\Controllers;

use App\Rules\InstitutionalEmail;
use App\Security\Security;
use App\Services\AccountInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    public function index(Request $r)
    {
        $q = DB::table('accounts');
        $state = $r->query('state', '');
        if ($state === 'active') {
            $q->where('active', true);
        } elseif ($state === 'inactive') {
            $q->where('active', false);
        } elseif ($state === 'pending') {
            $q->whereNull('email_verified_at')->where('legacy_access', false);
        }
        if ($r->filled('province')) {
            $q->where('province', $r->query('province'));
        }
        if ($r->filled('q')) {
            $term = mb_substr((string) $r->query('q'), 0, 100);
            $q->where(fn ($q) => $q->where('username', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
        }

        return view('users.index', ['users' => $q->orderBy('name')->paginate(30)->withQueryString(), 'provinces' => $this->provinces(), 'totals' => ['active' => DB::table('accounts')->where('active', true)->count(), 'inactive' => DB::table('accounts')->where('active', false)->count()]]);
    }

    private function provinces()
    {
        return json_decode(file_get_contents(base_path('legacy/provincias.json')), true);
    }

    public function store(Request $r)
    {
        $r->merge(['username' => strtolower(trim((string) $r->input('username'))), 'email' => strtolower(trim((string) $r->input('email')))]);
        $v = $r->validate(['username' => ['required', 'regex:/\A[a-z0-9][a-z0-9._-]{2,39}\z/', 'unique:accounts,username'], 'name' => 'required|string|max:200', 'email' => ['required', 'max:254', new InstitutionalEmail, 'unique:accounts,email'], 'province' => ['required', Rule::in(array_keys($this->provinces()))]]);
        $id = DB::transaction(function () use ($v) {
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
            $operator = DB::table('operadores')->insertGetId(['provincia' => $v['province'], 'usuario' => $v['username'], 'nombre' => $v['name'], 'perfil' => 'Técnico provincial APH', 'clave' => $hash, 'activo' => 0, 'revision' => 0]);

            return DB::table('accounts')->insertGetId($v + ['role' => 'technician', 'operator_id' => $operator, 'password' => $hash, 'active' => false, 'legacy_access' => false, 'created_at' => now(), 'updated_at' => now()]);
        });
        Security::event('account_created', null, 'account:'.$id);

        return redirect('/users')->with('notice', 'Cuenta creada pendiente de activación. El envío de correo se habilitará al configurar el servicio institucional.');
    }

    public function update(Request $r, int $id)
    {
        $account = DB::table('accounts')->find($id);
        abort_unless($account, 404);
        $action = $r->input('action');
        if ($action === 'state') {
            $v = $r->validate(['active' => 'required|boolean']);
            abort_if((string) $id === (string) $r->user()->id, 422, 'No puedes desactivar tu propia cuenta.');
            if ($v['active'] && ! $account->legacy_access && ! $account->email_verified_at) {
                return back()->withErrors(['active' => 'La cuenta debe completar su activación por correo.']);
            }
            DB::transaction(function () use ($id, $v, $account) {
                DB::table('accounts')->where('id', $id)->update(['active' => $v['active'], 'disabled_at' => $v['active'] ? null : now(), 'revision' => DB::raw('revision+1'), 'updated_at' => now()]);
                DB::table('sessions')->where('user_id', (string) $id)->delete();
                DB::table('account_invitations')->where('account_id', $id)->delete();
                if ($account->operator_id) {
                    DB::table('operadores')->where('id', $account->operator_id)->update(['activo' => $v['active']]);
                }
            });
            Security::event('account_state_changed', null, 'account:'.$id);
        } elseif ($action === 'edit') {
            $email = strtolower(trim((string) $r->input('email')));
            $r->merge(['email' => $email ?: null]);
            $v = $r->validate(['name' => 'required|string|max:200', 'email' => [$account->legacy_access ? 'nullable' : 'required', 'max:254', new InstitutionalEmail, Rule::unique('accounts', 'email')->ignore($id)]]);
            // Temporary grandfathered access is preserved; email is never marked verified here.
            $changed = ($v['email'] ?? null) !== $account->email;
            if ($changed) {
                $v['email_verified_at'] = null;
                if (! $account->legacy_access) {
                    $v['active'] = false;
                }
            }
            DB::transaction(function () use ($id, $v, $changed) {
                DB::table('accounts')->where('id', $id)->update($v + ['updated_at' => now(), 'revision' => DB::raw('revision+1')]);
                DB::table('sessions')->where('user_id', (string) $id)->delete();
                if ($changed) {
                    DB::table('account_invitations')->where('account_id', $id)->delete();
                }
            });
            Security::event('account_updated', null, 'account:'.$id);
        } else {
            abort(422);
        }

        return back()->with('notice', 'Usuario actualizado. Se revocaron sus sesiones anteriores.');
    }

    public function invite(Request $r, int $id)
    {
        return app(AccountInvitations::class)->send($r, $id);
    }
}
