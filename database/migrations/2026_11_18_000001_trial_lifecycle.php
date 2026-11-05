<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-61 trial lifecycle (read-only → closed → archived) for self-signup agencies.
 * Safe on a live PostgreSQL 13 DB: nullable columns only, no backfill, no data change. Existing agencies
 * (invited / admin-created) are never touched by the lifecycle, whatever these columns hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $t->timestamp('trial_readonly_at')->nullable();    // when the workspace became read-only
            $t->timestamp('trial_expired_at')->nullable();     // when it closed (status expired)
            $t->timestamp('trial_ending_notified_at')->nullable();  // "ends in 3 days" mail sent
            $t->timestamp('trial_ended_notified_at')->nullable();   // "trial ended, read-only" mail sent
            $t->timestamp('deletion_notified_at')->nullable();      // "data deleted in 7 days" mail sent
            $t->softDeletes();                                       // archived (reversible) by the lifecycle
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $t->dropSoftDeletes();
            $t->dropColumn(['trial_readonly_at', 'trial_expired_at', 'trial_ending_notified_at', 'trial_ended_notified_at', 'deletion_notified_at']);
        });
    }
};
