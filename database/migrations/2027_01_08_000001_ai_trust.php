<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R7 part 1 (D-299): per-client AI data scope (FS-02) and the once-a-month 80 % cap notice on own keys (EG-23). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $t) {
            $t->json('ai_scope')->nullable(); // null = everything on (D-299)
        });
        Schema::table('ai_keys', function (Blueprint $t) {
            $t->string('cap_noticed_month', 7)->nullable();
        });
        Schema::table('ai_night_runs', function (Blueprint $t) {
            $t->unsignedSmallInteger('redacted')->default(0);
            $t->unsignedTinyInteger('retries')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ai_night_runs', fn (Blueprint $t) => $t->dropColumn(['redacted', 'retries']));
        Schema::table('ai_keys', fn (Blueprint $t) => $t->dropColumn('cap_noticed_month'));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('ai_scope'));
    }
};
