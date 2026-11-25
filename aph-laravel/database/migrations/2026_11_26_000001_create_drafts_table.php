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
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('draft_key', 80);
            $table->unsignedInteger('record_version')->default(0);
            $table->mediumText('payload');
            $table->timestamp('updated_at');
            $table->unique(['account_id', 'draft_key']);
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Restaurar copia de seguridad para revertir: esta migración contiene borradores de fichas.');
    }
};
