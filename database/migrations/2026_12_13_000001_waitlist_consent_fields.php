<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P-1 waitlist (D-152, D-159): country, networks and a recorded consent.
 * Rows saved before this keep consent_at null and are never emailed until double opt-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $t) {
            $t->string('name', 120)->nullable()->change();
            $t->string('country', 2)->nullable()->after('email');
            $t->string('networks', 200)->nullable()->after('monthly_spend');
            $t->timestamp('consent_at')->nullable()->after('notes');
            $t->string('consent_text_version', 20)->nullable()->after('consent_at');
        });
    }

    public function down(): void
    {
        DB::table('waitlist_entries')->whereNull('name')->update(['name' => '']);

        Schema::table('waitlist_entries', function (Blueprint $t) {
            $t->dropColumn(['country', 'networks', 'consent_at', 'consent_text_version']);
            $t->string('name', 120)->nullable(false)->change();
        });
    }
};
