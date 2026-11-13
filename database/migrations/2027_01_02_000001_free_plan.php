<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-280 / D-286: the Free plan «الباقة المجانية» (1 seat, 1 client, 1 ad account, no price). Same networks as Solo.
 * Not offered anywhere yet: sign-up stays closed and the AI Workspace module is off; super admins can assign it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('plans')->where('key', 'free')->exists()) {
            return;
        }
        $id = DB::table('plans')->insertGetId(['key' => 'free', 'name' => 'Free', 'price_usd_month' => 0, 'seats' => 1, 'ad_accounts' => 1, 'clients' => 1,
            'is_active' => true, 'sort' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $solo = DB::table('plans')->where('key', 'solo')->value('id');
        if ($solo) {
            $rows = DB::table('ad_network_plan')->where('plan_id', $solo)->pluck('ad_network_id')->map(fn ($n) => ['ad_network_id' => $n, 'plan_id' => $id])->all();
            DB::table('ad_network_plan')->insert($rows);
        }
    }

    public function down(): void
    {
        $id = DB::table('plans')->where('key', 'free')->value('id');
        if ($id && ! DB::table('agencies')->where('plan', 'free')->exists()) {
            DB::table('ad_network_plan')->where('plan_id', $id)->delete();
            DB::table('plans')->whereKey($id)->delete();
        }
    }
};
