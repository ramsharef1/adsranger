<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #177 Solo plan (D-115 / D-140 / D-184 / D-187): 1 seat, 3 ad accounts, 5 clients; Starter's features minus the
 * monthly PDF (#57) and scheduled reports; same ad networks as Starter. Price lives in config/plan_features.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('plans')->where('key', 'solo')->exists()) {
            return;
        }
        $id = DB::table('plans')->insertGetId(['key' => 'solo', 'name' => 'Solo', 'seats' => 1, 'ad_accounts' => 3, 'clients' => 5, 'sort' => 0,
            'created_at' => now(), 'updated_at' => now()]);
        $starter = DB::table('plans')->where('key', 'starter')->value('id');
        if ($starter) {
            foreach (DB::table('ad_network_plan')->where('plan_id', $starter)->pluck('ad_network_id') as $nid) {
                DB::table('ad_network_plan')->insert(['ad_network_id' => $nid, 'plan_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('plans')->where('key', 'solo')->value('id');
        if ($id && ! DB::table('agencies')->where('plan', 'solo')->exists()) {
            DB::table('ad_network_plan')->where('plan_id', $id)->delete();
            DB::table('plans')->where('id', $id)->delete();
        }
    }
};
