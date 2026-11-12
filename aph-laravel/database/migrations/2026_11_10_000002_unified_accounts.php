<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('username', 40)->unique();
            $t->string('name', 200);
            $t->string('email')->nullable()->unique();
            $t->string('province', 60)->nullable();
            $t->string('role', 30)->default('technician');
            $t->foreignId('operator_id')->nullable()->constrained('operadores');
            $t->string('password');
            $t->boolean('active')->default(false);
            $t->boolean('legacy_access')->default(false);
            $t->timestamp('email_verified_at')->nullable();
            $t->unsignedInteger('revision')->default(0);
            $t->timestamp('last_login_at')->nullable();
            $t->timestamps();
        });
        Schema::create('account_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->unique()->constrained('accounts');
            $t->string('ticket_hash', 64)->unique();
            $t->string('code_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('sent_at');
        });
        DB::transaction(function () {
            foreach (DB::table('operadores')->get() as $o) {
                if (DB::table('accounts')->where('username', $o->usuario)->exists()) {
                    throw new RuntimeException('Usuario duplicado entre provincias: resolver antes de migrar.');
                }
                $name = DB::table('perfiles')->where('provincia', $o->provincia)->where('usuario', $o->usuario)->value('nombre') ?? $o->nombre;
                DB::table('accounts')->insert(['username' => $o->usuario, 'name' => $name, 'province' => $o->provincia, 'operator_id' => $o->id, 'password' => $o->clave, 'active' => $o->activo, 'legacy_access' => true, 'revision' => $o->revision, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach (DB::table('usuarios')->get() as $p) {
                if (DB::table('accounts')->where('username', $p->provincia)->exists()) {
                    throw new RuntimeException('Usuario provincial duplicado.');
                }
                $name = 'Técnico provincial APH · '.$p->provincia;
                $id = DB::table('operadores')->insertGetId(['provincia' => $p->provincia, 'usuario' => $p->provincia, 'nombre' => $name, 'perfil' => 'Técnico provincial APH', 'clave' => $p->clave, 'activo' => 1, 'revision' => 0]);
                DB::table('accounts')->insert(['username' => $p->provincia, 'name' => $name, 'province' => $p->provincia, 'operator_id' => $id, 'password' => $p->clave, 'active' => true, 'legacy_access' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('sessions')->delete();
            // Secrets belonged to the retired authentication method and are no longer usable.
            DB::table('auth_security')->delete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restaurar copia de seguridad para revertir esta migración de identidades.');
    }
};
