<?php

namespace App\Http\Controllers;

use App\Rules\InstitutionalEmail;
use App\Security\PasswordPolicy;
use App\Security\Security;
use App\Services\AccountInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        if ($r->input('action') === 'edit') {
            $r->merge(['edit_user_id' => (string) $id]);
        }
        try {
            return $this->applyUpdate($r, $id);
        } catch (ValidationException $error) {
            $error->redirectTo(route('users.index'));
            throw $error;
        }
    }

    private function applyUpdate(Request $r, int $id)
    {
        $account = DB::table('accounts')->find($id);
        abort_unless($account, 404);
        $action = $r->input('action');
        if ($action === 'state') {
            $v = $r->validate(['active' => 'required|boolean']);
            abort_if((string) $id === (string) $r->user()->id, 422, 'No puedes desactivar tu propia cuenta.');
            if ($v['active'] && ! $account->legacy_access && ! $account->email_verified_at) {
                return redirect()->route('users.index')->withErrors(['active' => 'La cuenta debe completar su activación por correo.']);
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
            $v = $r->validate([
                'first_name' => 'required|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'email' => [$account->legacy_access ? 'nullable' : 'required', 'max:254', new InstitutionalEmail, Rule::unique('accounts', 'email')->ignore($id)],
                'password' => ['nullable', 'string', function ($attribute, $value, $fail) {
                    if ($error = PasswordPolicy::error($value)) {
                        $fail($error);
                    }
                }],
                'role' => ['required', Rule::in(['technician', 'superadmin'])],
                'province' => ['required_if:role,technician', 'nullable', Rule::in(array_keys($this->provinces()))],
            ]);
            if ((string) $id === (string) $r->user()->id && $v['role'] !== 'superadmin') {
                throw ValidationException::withMessages(['role' => 'No puedes quitarte el acceso de superadministrador.']);
            }
            $v['name'] = trim($v['first_name'].' '.($v['last_name'] ?? ''));
            if (mb_strlen($v['name']) > 200) {
                throw ValidationException::withMessages(['first_name' => 'El nombre completo admite hasta 200 caracteres.']);
            }
            $v['province'] = $v['role'] === 'superadmin' ? null : $v['province'];
            if (! empty($v['password'])) {
                $v['password'] = Hash::make($v['password']);
            } else {
                unset($v['password']);
            }
            DB::transaction(function () use ($id, $v) {
                $current = DB::table('accounts')->where('id', $id)->lockForUpdate()->first();
                $emailChanged = ($v['email'] ?? null) !== $current->email;
                $identityChanged = $v['province'] !== $current->province || $v['role'] !== $current->role;
                if ($emailChanged) {
                    $v['email_verified_at'] = null;
                    if (! $current->legacy_access) {
                        $v['active'] = false;
                    }
                }
                $active = $v['active'] ?? $current->active;
                if ($current->operator_id && $identityChanged) {
                    DB::table('operadores')->where('id', $current->operator_id)->update(['activo' => false]);
                }
                if ($v['role'] === 'technician') {
                    // Create/reuse a provincial identity without moving historical fichas.
                    $operator = DB::table('operadores')->where('provincia', $v['province'])->where('usuario', $current->username)->first();
                    $operatorValues = ['nombre' => $v['name'], 'clave' => $v['password'] ?? $current->password, 'activo' => $active, 'perfil' => 'Técnico provincial APH'];
                    if ($operator) {
                        DB::table('operadores')->where('id', $operator->id)->update($operatorValues);
                        $v['operator_id'] = $operator->id;
                    } else {
                        $v['operator_id'] = DB::table('operadores')->insertGetId($operatorValues + ['provincia' => $v['province'], 'usuario' => $current->username, 'revision' => 0]);
                    }
                } else {
                    $v['operator_id'] = null;
                }
                $oldProfile = DB::table('perfiles')->where('provincia', $current->province ?? 'nacional')->where('usuario', $current->username)->first();
                if ($oldProfile) {
                    DB::table('perfiles')->updateOrInsert(['provincia' => $v['province'] ?? 'nacional', 'usuario' => $current->username], ['nombre' => $v['name'], 'imagen' => $oldProfile->imagen, 'imagen_tipo' => $oldProfile->imagen_tipo]);
                }
                DB::table('accounts')->where('id', $id)->update($v + ['updated_at' => now(), 'revision' => DB::raw('revision+1')]);
                DB::table('sessions')->where('user_id', (string) $id)->delete();
                DB::table('account_invitations')->where('account_id', $id)->delete();
                if ($identityChanged) {
                    Security::event('account_permissions_changed', null, 'account:'.$id);
                }
                if (isset($v['password'])) {
                    Security::event('account_password_reset', null, 'account:'.$id);
                }
            });
            Security::event('account_updated', null, 'account:'.$id);
        } else {
            abort(422);
        }

        if ((string) $id === (string) Auth::id()) {
            $fresh = Auth::getProvider()->retrieveById((string) $id);
            if (! $fresh) {
                return Security::logout($r, 'account_requires_activation');
            }
            Auth::setUser($fresh);
            $r->session()->regenerate(true);
            $r->session()->put('credential', Security::stamp($fresh));
        }

        return redirect()->route('users.index')->with('notice', 'Usuario actualizado correctamente. Se revocaron las otras sesiones de la cuenta.');
    }

    public function invite(Request $r, int $id)
    {
        return app(AccountInvitations::class)->send($r, $id);
    }
}
