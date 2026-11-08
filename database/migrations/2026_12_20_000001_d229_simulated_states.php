<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-229 (honesty): rows that claimed a network action that never happened are relabelled `simulated`,
 * and canned "panic" client notes are marked withdrawn (kept, not deleted). Both hubs stay off.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('custom_audience_syncs')->where('status', 'synced')->update(['status' => 'simulated', 'last_synced_at' => null]);
        DB::table('network_accelerator_events')->whereIn('status', ['capi_dispatched', 'syndicated_to_paid', 'queued', 'attributed'])->update(['status' => 'simulated']);
        DB::table('agency_operational_audits')->where('audit_type', 'client_panic_defuser')->update(['status' => 'withdrawn']);
    }

    public function down(): void
    {
        // One-way relabel: the original fake states are not restored.
    }
};
