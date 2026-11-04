<?php

namespace App\Support;

use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Connection;
use App\Sync\SyncService;

/** Sample clients + 90 days of Meta-style data, so screens can be tried before any network is approved. */
class DemoData
{
    public const TAG = 'demo';

    public static function seed(Agency $a, int $days = 90): array
    {
        mt_srand(crc32($a->slug));
        $meta = AdNetwork::where('key', 'meta')->firstOrFail();
        $conn = Connection::firstOrCreate(['agency_id' => $a->id, 'ad_network_id' => $meta->id, 'external_user_id' => self::TAG],
            ['status' => 'active', 'environment' => self::TAG, 'external_user_name' => 'Demo data']);
        $specs = [
            ['Demo · Smile Dental Clinic', 'clinic', 'JO', 'JOD', [['Leads – Amman', 'OUTCOME_LEADS', 'LEAD_GENERATION', null, 25, 4.5], ['Calls – Whitening', 'OUTCOME_LEADS', 'QUALITY_CALL', null, 15, 6]]],
            ['Demo · Hope Charity', 'charity', 'SA', 'USD', [['Donations – Ramadan', 'OUTCOME_SALES', 'OFFSITE_CONVERSIONS', 'DONATE', 40, 9], ['Messages – Volunteers', 'OUTCOME_ENGAGEMENT', 'CONVERSATIONS', null, 10, 1.8]]],
            ['Demo · Souq Store', 'ecommerce', 'AE', 'AED', [['Sales – Catalog', 'OUTCOME_SALES', 'OFFSITE_CONVERSIONS', 'PURCHASE', 120, 35], ['Traffic – New arrivals', 'OUTCOME_TRAFFIC', 'LINK_CLICKS', null, 30, 0.6]]],
        ];
        $out = [];
        foreach ($specs as $i => [$name, $vertical, $market, $cur, $camps]) {
            $client = Client::firstOrCreate(['agency_id' => $a->id, 'name' => $name], ['vertical' => $vertical, 'market' => $market, 'currency' => $cur,
                'monthly_budget' => array_sum(array_column($camps, 4)) * 30, 'notes' => 'Sample data. Remove it from /admin → Agencies.']);
            $acct = AdAccount::firstOrCreate(['connection_id' => $conn->id, 'external_id' => 'act_demo'.($i + 1)], [
                'agency_id' => $a->id, 'ad_network_id' => $meta->id, 'client_id' => $client->id, 'name' => $name, 'currency' => $cur,
                'timezone' => $a->timezone, 'status' => '1', 'is_active' => false]);
            $payload = ['campaigns' => [], 'ad_sets' => [], 'ads' => [], 'rows' => []];
            foreach ($camps as $j => [$cName, $obj, $goal, $event, $budget, $cpr]) {
                $cid = "demo{$i}c{$j}";
                $sid = "demo{$i}s{$j}";
                $payload['campaigns'][] = ['external_id' => $cid, 'name' => $cName, 'status' => 'ACTIVE', 'objective' => $obj];
                $payload['ad_sets'][] = ['external_id' => $sid, 'name' => $cName.' · Broad', 'campaign' => $cid, 'status' => 'ACTIVE',
                    'optimization_goal' => $goal, 'custom_event' => $event, 'daily_budget' => $budget,
                    'end_time' => $j === 1 && $i === 0 ? now()->addDays(2)->toIso8601String() : null];
                $type = ResultResolver::metaType($goal, $obj, $event);
                foreach ([1, 2, 3] as $k) {
                    $aid = "demo{$i}a{$j}{$k}";
                    $payload['ads'][] = ['external_id' => $aid, 'name' => "$cName · Creative $k", 'campaign' => $cid, 'ad_set' => $sid, 'status' => 'ACTIVE',
                        'thumbnail_url' => null];
                    for ($d = $days; $d >= 1; $d--) {
                        $date = now($a->timezone ?: 'UTC')->subDays($d)->toDateString();
                        $spend = round($budget / 3 * (0.6 + mt_rand(0, 80) / 100), 2);
                        $res = $spend / ($cpr * (0.7 + mt_rand(0, 60) / 100) * (1 + ($k - 2) * 0.15));
                        // Tracking break in the clinic's lead campaign yesterday, so the alert rules have something to show.
                        if ($i === 0 && $j === 0 && $d === 1) {
                            $res = 0;
                        }
                        $res = (int) round($res);
                        $impr = (int) ($spend * mt_rand(180, 420));
                        $actions = match ($type) {
                            'lead' => [['action_type' => 'lead', 'value' => $res]],
                            'call' => [['action_type' => 'click_to_call_native_call_placed', 'value' => $res], ['action_type' => 'click_to_call_native_20s_call_connect', 'value' => (int) ($res * 0.6)]],
                            'message' => [['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => $res]],
                            'purchase' => [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => $res]],
                            default => [],
                        };
                        $values = $type === 'purchase' ? [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => $res * mt_rand(80, 260)]] : [];
                        $row = ['spend' => $spend, 'impressions' => $impr, 'reach' => (int) ($impr * 0.7), 'clicks' => (int) ($impr * 0.02),
                            'inline_link_clicks' => $type === 'link_click' ? $res : (int) ($impr * 0.012), 'actions' => $actions, 'action_values' => $values];
                        $payload['rows'][] = ['date' => $date, 'level' => 'ad', 'campaign' => $cid, 'ad_set' => $sid, 'ad' => $aid] + ResultResolver::meta($row, $type);
                    }
                }
            }
            $from = now($a->timezone ?: 'UTC')->subDays($days)->toDateString();
            $out[$name] = SyncService::ingest($acct, $payload, $from, now()->toDateString());
            $acct->update(['last_synced_at' => now(), 'sync_status' => 'ok', 'backfilled_at' => now()]);
        }

        return $out;
    }

    public static function remove(Agency $a): void
    {
        $conn = Connection::where('agency_id', $a->id)->where('external_user_id', self::TAG)->first();
        if ($conn) {
            \App\Models\DailyMetric::whereIn('ad_account_id', $conn->adAccounts()->pluck('id'))->delete();
            Client::whereIn('id', $conn->adAccounts()->pluck('client_id')->filter())->delete();
            $conn->delete(); // cascades to ad accounts, structure and metrics
        }
    }
}
