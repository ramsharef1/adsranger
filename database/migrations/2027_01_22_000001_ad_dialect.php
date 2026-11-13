<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** PS-22 (R5): the dialect of an ad, set by the agency (otherwise guessed from the ad name). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $t): void {
            $t->string('dialect', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $t): void {
            $t->dropColumn('dialect');
        });
    }
};
