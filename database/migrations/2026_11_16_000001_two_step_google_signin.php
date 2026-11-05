<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #74 two-step verification (Filament app authentication: TOTP + recovery codes) and Google sign-in.
 * Safe on a live PostgreSQL 13 DB: nullable columns only, no backfill. Nobody has 2FA after deploy
 * until they set it up; nobody has google_id until they first sign in with Google.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Both are stored encrypted by Filament's model traits (APP_KEY), so `text`, not `string`.
            $t->text('app_authentication_secret')->nullable();
            $t->text('app_authentication_recovery_codes')->nullable();
            $t->string('google_id', 64)->nullable()->unique();
        });

        // Per-agency "Require two-step verification for all members" (owner/admin, Agency settings). NULL = off.
        Schema::table('agencies', fn (Blueprint $t) => $t->boolean('require_two_factor')->nullable());
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('require_two_factor'));
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['google_id']);
            $t->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'google_id']);
        });
    }
};
