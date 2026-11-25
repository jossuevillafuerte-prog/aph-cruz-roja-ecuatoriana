<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_events', function (Blueprint $t) {
            $t->string('reference', 300)->nullable();
            $t->string('target', 80)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restaurar copia de seguridad para revertir: esta migración contiene datos de cuentas, fichas o auditoría.');
    }
};
