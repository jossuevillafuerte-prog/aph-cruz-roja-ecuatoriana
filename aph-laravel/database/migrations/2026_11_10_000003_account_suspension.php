<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', fn (Blueprint $t) => $t->timestamp('disabled_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('accounts', fn (Blueprint $t) => $t->dropColumn('disabled_at'));
    }
};
