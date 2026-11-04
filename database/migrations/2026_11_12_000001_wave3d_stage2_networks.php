<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Wave 3d: Stage 2 networks + connections that hold an agency's own API credentials (encrypted). */
return new class extends Migration
{
    protected const NETWORKS = ['amazon_ads', 'pinterest', 'reddit', 'taboola', 'outbrain', 'mgid', 'revcontent', 'apple_ads', 'yahoo_dsp'];

    public function up(): void
    {
        Schema::table('connections', fn (Blueprint $t) => $t->text('credentials')->nullable()->after('refresh_token'));

        $agencyPlan = DB::table('plans')->where('key', 'agency')->value('id');
        foreach (self::NETWORKS as $key) {
            $n = config('ad_networks.'.$key);
            if (! $n) {
                continue;
            }
            $id = DB::table('ad_networks')->where('key', $key)->value('id')
                ?? DB::table('ad_networks')->insertGetId(['key' => $key, 'name' => $n['name'], 'category' => $n['category'], 'stage' => $n['stage'],
                    'created_at' => now(), 'updated_at' => now()]);
            // Same tier as the other secondary networks (TikTok, Snap, LinkedIn): Agency plan. Editable in /admin > Plans.
            if ($agencyPlan && ! DB::table('ad_network_plan')->where(['ad_network_id' => $id, 'plan_id' => $agencyPlan])->exists()) {
                DB::table('ad_network_plan')->insert(['ad_network_id' => $id, 'plan_id' => $agencyPlan]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('connections', fn (Blueprint $t) => $t->dropColumn('credentials'));
        // Network rows stay (archive, never delete); they are switched off by default.
    }
};
