<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Crea técnicos provinciales inactivos hasta que activen su cuenta por correo.
 */
class PendingAccounts
{
    /**
     * @param  array{username: string, name: string, email: string, province: string, first_name?: string, last_name?: string}  $values
     */
    public static function create(array $values): int
    {
        return DB::transaction(function () use ($values) {
            $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
            $operator = DB::table('operadores')->insertGetId(['provincia' => $values['province'], 'usuario' => $values['username'], 'nombre' => $values['name'], 'perfil' => 'Técnico provincial APH', 'clave' => $hash, 'activo' => 0, 'revision' => 0]);

            return DB::table('accounts')->insertGetId($values + ['role' => 'technician', 'operator_id' => $operator, 'password' => $hash, 'active' => false, 'legacy_access' => false, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
