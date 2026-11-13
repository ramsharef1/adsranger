<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R3 (D-290, D-295): what an import did (PS-07 re-upload diff, PS-26 cleaned names). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_imports', function (Blueprint $t) {
            $t->unsignedInteger('rows_updated')->default(0);
            $t->unsignedInteger('rows_skipped')->default(0);
            $t->unsignedInteger('names_cleaned')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('data_imports', function (Blueprint $t) {
            $t->dropColumn(['rows_updated', 'rows_skipped', 'names_cleaned']);
        });
    }
};
