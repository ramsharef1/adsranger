<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** FS-21 (R4): who watches alerts each day of the week (ISO 1 = Monday … 7 = Sunday → user id). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->json('on_call')->nullable());
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('on_call'));
    }
};
