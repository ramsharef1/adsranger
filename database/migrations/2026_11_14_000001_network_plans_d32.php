<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * D-32 network tiers (ADSR-060): TikTok + Snapchat from Starter, Microsoft + LinkedIn from Pro.
 * Stage 2 networks stay Agency-only (D-01). Only adds missing rows; admin edits are kept.
 */
return new class extends Migration
{
    private const ADD = [
        'tiktok' => ['starter', 'pro'],
        'snapchat' => ['starter', 'pro'],
        'microsoft' => ['pro'],
        'linkedin' => ['pro'],
    ];

    public function up(): void
    {
        foreach (self::ADD as $net => $plans) {
            $nid = DB::table('ad_networks')->where('key', $net)->value('id');
            foreach ($plans as $plan) {
                $pid = DB::table('plans')->where('key', $plan)->value('id');
                if ($nid && $pid && ! DB::table('ad_network_plan')->where(['ad_network_id' => $nid, 'plan_id' => $pid])->exists()) {
                    DB::table('ad_network_plan')->insert(['ad_network_id' => $nid, 'plan_id' => $pid]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::ADD as $net => $plans) {
            $nid = DB::table('ad_networks')->where('key', $net)->value('id');
            $pids = DB::table('plans')->whereIn('key', $plans)->pluck('id');
            if ($nid) {
                DB::table('ad_network_plan')->where('ad_network_id', $nid)->whereIn('plan_id', $pids)->delete();
            }
        }
    }
};
