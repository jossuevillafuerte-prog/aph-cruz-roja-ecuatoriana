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
        Schema::table('security_events', fn (Blueprint $t) => $t->dropColumn(['reference', 'target']));
    }
};
