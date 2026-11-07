<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** O-230 / D-189: the optional "You are…" answer on the waitlist (agency, freelancer, business running its own ads). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_entries', fn (Blueprint $t) => $t->string('audience', 16)->nullable()->after('agency_name'));
    }

    public function down(): void
    {
        Schema::table('waitlist_entries', fn (Blueprint $t) => $t->dropColumn('audience'));
    }
};
