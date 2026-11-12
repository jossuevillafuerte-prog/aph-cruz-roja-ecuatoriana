<?php

namespace App\Auth;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;

class ProvincialProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        if (! ctype_digit((string) $identifier)) {
            return null;
        }
        $r = DB::table('accounts')->where('id', $identifier)->where('active', true)->first();
        if (! $r || (! $r->legacy_access && ! $r->email_verified_at)) {
            return null;
        }

        return new GenericUser(array_merge((array) $r, ['id' => (string) $r->id, 'provincia' => $r->province, 'usuario' => $r->username, 'nombre' => $r->name, 'clave' => $r->password, 'remember_token' => null]));
    }

    public function retrieveByCredentials(array $credentials)
    {
        $id = DB::table('accounts')->where('username', $credentials['usuario'] ?? '')->value('id');

        return $id ? $this->retrieveById($id) : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return password_verify($credentials['password'] ?? '', $user->getAuthPassword());
    }

    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token) {}

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false) {}
}
