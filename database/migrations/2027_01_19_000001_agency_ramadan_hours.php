<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** PS-19 (R5, D-307): Ramadan hours mode per workspace. Null counts as on; null digest time counts as 21:00. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t): void {
            $t->boolean('ramadan_hours')->nullable();
            $t->string('ramadan_digest_at', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $t): void {
            $t->dropColumn(['ramadan_hours', 'ramadan_digest_at']);
        });
    }
};
