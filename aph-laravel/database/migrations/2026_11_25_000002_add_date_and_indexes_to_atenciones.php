<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('atenciones', function (Blueprint $table) {
            $table->string('fecha', 10)->nullable()->virtualAs("LEFT(JSON_UNQUOTE(JSON_EXTRACT(datos, '$.B')), 10)");
        });
        Schema::table('atenciones', function (Blueprint $table) {
            $table->index(['provincia', 'eliminado', 'fecha']);
            $table->index(['provincia', 'eliminado', 'estado']);
            $table->index(['eliminado', 'fecha']);
            $table->index(['eliminado', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Restaurar copia de seguridad para revertir: el panel y los listados dependen de estas columnas.');
    }
};
