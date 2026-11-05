<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #158 self-signup (flag `signup.open`) + D-61 trial.
 * Safe on a live PostgreSQL 13 DB: only nullable columns and a new table; backfills are plain UPDATEs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Platform settings a super admin can change in /admin (e.g. signup.open, trial domain allowlist).
        Schema::create('platform_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 120)->unique();
            $t->json('value')->nullable();
            $t->timestamps();
        });

        // How an agency came in: invite / admin / self_signup.
        Schema::table('agencies', fn (Blueprint $t) => $t->string('signup_source', 20)->nullable());
        DB::table('agencies')->whereNull('signup_source')
            ->where(fn ($q) => $q->whereNull('owner_id')->orWhereIn('owner_id', DB::table('users')->where('role', 'super_admin')->select('id')))
            ->update(['signup_source' => 'admin']);
        DB::table('agencies')->whereNull('signup_source')->update(['signup_source' => 'invite']);

        // Self-signup data held until the email is verified (agency name, country, locale); cleared after.
        Schema::table('users', fn (Blueprint $t) => $t->json('signup_payload')->nullable());

        // Email verification now gates /app. Existing accounts came in by invite or were made by an admin,
        // so they count as verified: nobody already using the app gets locked out by this release.
        DB::table('users')->whereNull('email_verified_at')->whereNotNull('created_at')->update(['email_verified_at' => DB::raw('created_at')]);
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('signup_payload'));
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('signup_source'));
        Schema::dropIfExists('platform_settings');
    }
};
