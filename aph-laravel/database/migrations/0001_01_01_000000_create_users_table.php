<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $t) {
            $t->string('provincia', 60)->primary();
            $t->string('clave');
        });
        Schema::create('operadores', function (Blueprint $t) {
            $t->id();
            $t->string('provincia', 60);
            $t->string('usuario', 40);
            $t->string('nombre');
            $t->string('perfil');
            $t->string('clave');
            $t->boolean('activo')->default(true);
            $t->integer('revision')->default(0);
            $t->unique(['provincia', 'usuario']);
            $t->foreign('provincia')->references('provincia')->on('usuarios');
        });
        Schema::create('perfiles', function (Blueprint $t) {
            $t->string('provincia', 60);
            $t->string('usuario', 40);
            $t->string('nombre');
            $t->binary('imagen')->nullable();
            $t->string('imagen_tipo')->nullable();
            $t->primary(['provincia', 'usuario']);
        });
        Schema::create('recursos', function (Blueprint $t) {
            $t->id();
            $t->string('provincia', 60);
            $t->string('numero', 300);
            $t->string('ubicacion', 300);
            $t->string('capacidad', 300);
            $t->string('combustible', 300);
            $t->string('tipo', 300)->default('Ambulancia');
            $t->unique(['provincia', 'numero']);
            $t->foreign('provincia')->references('provincia')->on('usuarios');
        });
        Schema::create('atenciones', function (Blueprint $t) {
            $t->id();
            $t->integer('version');
            $t->string('estado', 20);
            $t->json('datos');
            $t->string('provincia', 60);
            $t->foreignId('recurso_id')->nullable()->constrained('recursos');
            $t->foreignId('responsable_id')->nullable()->constrained('operadores');
            $t->string('eliminado')->nullable();
            $t->index(['provincia', 'id']);
            $t->foreign('provincia')->references('provincia')->on('usuarios');
        });
        Schema::create('cambios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('atencion')->constrained('atenciones');
            $t->string('fecha');
            $t->string('autor');
            $t->text('motivo');
            $t->longText('detalle');
        });
        Schema::create('geocodificacion', function (Blueprint $t) {
            $t->string('clave', 64)->primary();
            $t->text('direccion');
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
        Schema::create('auth_security', function (Blueprint $t) {
            $t->string('identity')->primary();
            $t->text('secret');
            $t->bigInteger('last_step')->default(0);
            $t->text('recovery');
        });
        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->string('identity')->nullable()->index();
            $t->string('event', 60);
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->index();
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::getConnection()->statement('ALTER TABLE perfiles MODIFY imagen MEDIUMBLOB NULL');
        }
    }

    public function down(): void
    {
        foreach (['security_events', 'auth_security', 'sessions', 'geocodificacion', 'cambios', 'atenciones', 'recursos', 'perfiles', 'operadores', 'usuarios'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
