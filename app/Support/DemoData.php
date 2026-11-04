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
            $payload['breakdowns'] = static::breakdowns($payload['rows'], $market, now($a->timezone ?: 'UTC')->subDay()->toDateString());
            $from = now($a->timezone ?: 'UTC')->subDays($days)->toDateString();
            $out[$name] = SyncService::ingest($acct, $payload, $from, now()->toDateString());
            $acct->update(['last_synced_at' => now(), 'sync_status' => 'ok', 'backfilled_at' => now()]);
        }

        $out += static::google($a, min($days, 90));

        return $out;
    }

    /** Sample Google Ads account for the clinic and the store: Search + PMax, search terms, impression share, Quality Score, asset groups. */
    protected static function google(Agency $a, int $days): array
    {
        $net = AdNetwork::where('key', 'google_ads')->first();
        if (! $net) {
            return [];
        }
        $conn = Connection::firstOrCreate(['agency_id' => $a->id, 'ad_network_id' => $net->id, 'external_user_id' => self::TAG],
            ['status' => 'active', 'environment' => self::TAG, 'external_user_name' => 'Demo data']);
        $tz = $a->timezone ?: 'UTC';
        $specs = [
            ['Demo · Smile Dental Clinic', 'JOD', 'https://example.com/smile-dental', [
                ['g1', 'Search – Dental implants', 'SEARCH', 18, 6.0, ['dental implants amman' => [1, 0.12], 'زراعة اسنان عمان' => [1, 0.1], 'teeth implant price' => [1, 0.08], 'free dental implants' => [0, 0], 'dental implant course' => [0, 0], 'best dentist near me' => [1, 0.05]]],
                ['g2', 'Search – Teeth whitening', 'SEARCH', 10, 4.0, ['teeth whitening amman' => [1, 0.1], 'تبييض الاسنان' => [1, 0.09], 'whitening strips' => [0, 0], 'teeth whitening at home' => [0, 0.01]]],
            ]],
            ['Demo · Souq Store', 'AED', 'https://example.com/souq-store', [
                ['g3', 'PMax – All products', 'PERFORMANCE_MAX', 60, 22.0, []],
                ['g4', 'Search – Brand', 'SEARCH', 15, 8.0, ['souq store' => [1, 0.2], 'souq store dubai' => [1, 0.18], 'souq store careers' => [0, 0]]],
            ]],
        ];
        $out = [];
        foreach ($specs as $i => [$clientName, $cur, $site, $camps]) {
            $client = Client::where('agency_id', $a->id)->where('name', $clientName)->first();
            if (! $client) {
                continue;
            }
            $acct = AdAccount::firstOrCreate(['connection_id' => $conn->id, 'external_id' => '99900000'.($i + 1)], [
                'agency_id' => $a->id, 'ad_network_id' => $net->id, 'client_id' => $client->id, 'name' => $clientName.' · Google', 'currency' => $cur,
                'timezone' => $tz, 'status' => 'ENABLED', 'is_active' => false]);
            $p = ['campaigns' => [], 'ad_sets' => [], 'ads' => [], 'rows' => [], 'google' => ['campaign_daily' => [], 'search_terms' => [], 'keywords' => [], 'asset_groups' => [],
                'landing_urls' => [$site.'?utm_source=google' => 340, $site.'/offers' => 120, 'https://example.com/this-page-does-not-exist-adsranger' => 25]]];
            foreach ($camps as [$cid, $name, $channel, $budget, $cpa, $terms]) {
                $p['campaigns'][] = ['external_id' => $cid, 'name' => $name, 'status' => 'ENABLED', 'channel' => $channel, 'daily_budget' => $budget];
                for ($d = $days; $d >= 1; $d--) {
                    $date = now($tz)->subDays($d)->toDateString();
                    $cost = round($budget * (0.7 + mt_rand(0, 50) / 100), 2);
                    $conv = round($cost / ($cpa * (0.7 + mt_rand(0, 60) / 100)), 1);
                    $p['rows'][] = ['date' => $date, 'level' => 'campaign', 'campaign' => $cid, 'ad_set' => null, 'ad' => null]
                        + ResultResolver::google(['cost_micros' => $cost * 1e6, 'impressions' => (int) ($cost * mt_rand(40, 90)), 'clicks' => (int) ($cost * mt_rand(2, 4)),
                            'conversions' => $conv, 'conversions_value' => $channel === 'PERFORMANCE_MAX' ? $conv * mt_rand(90, 240) : 0]);
                    if ($channel === 'SEARCH') {
                        $p['google']['campaign_daily'][] = ['campaign' => $cid, 'date' => $date, 'search_is' => 0.35 + mt_rand(0, 30) / 100,
                            'lost_budget' => $budget < 15 ? 0.25 + mt_rand(0, 10) / 100 : 0.05, 'lost_rank' => 0.2 + mt_rand(0, 15) / 100,
                            'top_is' => 0.5 + mt_rand(0, 20) / 100, 'abs_top_is' => 0.2 + mt_rand(0, 15) / 100];
                        if ($d <= 30) {
                            foreach ($terms as $t => [$good, $rate]) {
                                $clicks = mt_rand(0, 4);
                                if (! $clicks) {
                                    continue;
                                }
                                $p['google']['search_terms'][] = ['campaign' => $cid, 'ad_group' => $cid.'ag', 'term' => $t, 'status' => 'NONE', 'date' => $date,
                                    'impressions' => $clicks * mt_rand(8, 20), 'clicks' => $clicks, 'cost' => round($clicks * $cpa * 0.15, 2),
                                    'conversions' => $good && mt_rand(0, 99) < $rate * 300 ? 1 : 0, 'conv_value' => 0];
                            }
                        }
                    }
                }
                foreach (array_keys($terms) as $k => $t) {
                    if ($terms[$t][0]) {
                        $p['google']['keywords'][] = ['campaign' => $cid, 'ad_group' => $cid.'ag', 'id' => $cid.$k, 'text' => $t, 'match_type' => $k % 2 ? 'PHRASE' : 'EXACT',
                            'status' => 'ENABLED', 'qs' => [8, 6, 4, 7, 3, 9][$k % 6], 'creative_qs' => ['ABOVE_AVERAGE', 'AVERAGE', 'BELOW_AVERAGE'][$k % 3],
                            'landing_qs' => ['AVERAGE', 'ABOVE_AVERAGE', 'BELOW_AVERAGE'][$k % 3], 'ctr_qs' => ['ABOVE_AVERAGE', 'AVERAGE', 'BELOW_AVERAGE'][($k + 1) % 3],
                            'impressions' => mt_rand(800, 4000), 'clicks' => mt_rand(40, 200), 'cost' => mt_rand(60, 300), 'conversions' => mt_rand(2, 25)];
                    }
                }
                if ($channel === 'PERFORMANCE_MAX') {
                    foreach ([['All products', 'GOOD'], ['Summer sale', 'POOR']] as $g => [$gName, $strength]) {
                        $p['google']['asset_groups'][] = ['campaign' => $cid, 'campaign_name' => $name, 'id' => $cid.'grp'.$g, 'name' => $gName, 'status' => 'ENABLED',
                            'ad_strength' => $strength, 'impressions' => mt_rand(20000, 60000), 'clicks' => mt_rand(500, 1500), 'cost' => $g ? 420 : 1300, 'conversions' => $g ? 9 : 58,
                            'conv_value' => $g ? 1800 : 12600, 'assets' => [
                                ['field' => 'HEADLINE', 'label' => 'BEST', 'type' => 'TEXT', 'text' => 'Free delivery across the UAE'],
                                ['field' => 'HEADLINE', 'label' => 'GOOD', 'type' => 'TEXT', 'text' => 'تسوق الآن'],
                                ['field' => 'HEADLINE', 'label' => 'LOW', 'type' => 'TEXT', 'text' => 'Great products'],
                                ['field' => 'DESCRIPTION', 'label' => 'LEARNING', 'type' => 'TEXT', 'text' => 'New arrivals every week'],
                                ['field' => 'MARKETING_IMAGE', 'label' => $g ? 'LOW' : 'GOOD', 'type' => 'IMAGE', 'text' => 'Banner 1200x628'],
                            ]];
                    }
                }
            }
            $from = now($tz)->subDays($days)->toDateString();
            $out[$clientName.' · Google'] = SyncService::ingest($acct, $p, $from, now()->toDateString());
            $acct->update(['last_synced_at' => now(), 'sync_status' => 'ok', 'backfilled_at' => now()]);
        }

        return $out;
    }

    /** Sample breakdowns (hour, placement, region, age × gender, 30-day reach) spread from the daily rows. */
    protected static function breakdowns(array $rows, string $market, string $yesterday): array
    {
        $hours = [0 => 3, 1 => 2, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 2, 8 => 3, 9 => 4, 10 => 5, 11 => 5, 12 => 5, 13 => 5, 14 => 5, 15 => 5,
            16 => 6, 17 => 6, 18 => 6, 19 => 7, 20 => 8, 21 => 9, 22 => 8, 23 => 5];
        $places = ['facebook:feed' => 30, 'instagram:feed' => 22, 'instagram:story' => 16, 'instagram:instagram_reels' => 14, 'facebook:facebook_reels' => 8,
            'facebook:story' => 5, 'audience_network:classic' => 3, 'messenger:messenger_inbox' => 2];
        $regions = ['JO' => ['Amman' => 55, 'Irbid' => 15, 'Zarqa' => 12, 'Aqaba' => 6, 'Balqa' => 7, 'Madaba' => 5],
            'SA' => ['Riyadh Region' => 34, 'Makkah Region' => 27, 'Eastern Province' => 18, 'Madinah Region' => 8, 'Asir Region' => 6, 'Qassim Region' => 7],
            'AE' => ['Dubai' => 44, 'Abu Dhabi' => 28, 'Sharjah' => 16, 'Ajman' => 6, 'Ras al-Khaimah' => 4, 'Fujairah' => 2]][$market] ?? ['Unknown' => 100];
        $ages = [];
        foreach (['18-24' => 18, '25-34' => 34, '35-44' => 24, '45-54' => 14, '55-64' => 7, '65+' => 3] as $age => $w) {
            $ages["$age|female"] = $w * 0.55;
            $ages["$age|male"] = $w * 0.45;
        }
        // Cost-efficiency multipliers so the insights show contrast (results weight ÷ spend weight).
        $eff = fn (string $k) => 0.6 + (crc32($k) % 90) / 100;
        $daily = [];
        foreach ($rows as $r) {
            $k = $r['date'].'|'.$r['result_type'];
            $d = $daily[$k] ?? ['date' => $r['date'], 'result_type' => $r['result_type'], 'spend' => 0, 'impressions' => 0, 'reach' => 0, 'clicks' => 0, 'link_clicks' => 0, 'results' => 0];
            foreach (['spend', 'impressions', 'reach', 'clicks', 'link_clicks', 'results'] as $m) {
                $d[$m] += $r[$m] ?? 0;
            }
            $daily[$k] = $d;
        }
        $spread = function (array $weights, string $dim) use ($daily, $eff) {
            $sum = array_sum($weights);
            $resW = [];
            foreach ($weights as $key => $w) {
                $resW[$key] = $w * $eff($dim.$key);
            }
            $resSum = array_sum($resW);
            $out = [];
            foreach ($daily as $d) {
                foreach ($weights as $key => $w) {
                    $f = $w / $sum;
                    $out[] = ['date' => $d['date'], 'key' => (string) $key, 'result_type' => $d['result_type'], 'spend' => round($d['spend'] * $f, 4),
                        'impressions' => (int) ($d['impressions'] * $f), 'reach' => (int) ($d['reach'] * $f), 'clicks' => (int) ($d['clicks'] * $f),
                        'link_clicks' => (int) ($d['link_clicks'] * $f), 'results' => round($d['results'] * $resW[$key] / $resSum, 2)];
                }
            }

            return $out;
        };
        $out = ['hour' => $spread($hours, 'h'), 'placement' => $spread($places, 'p'), 'region' => $spread($regions, 'r'), 'age_gender' => $spread($ages, 'a')];

        // 30-day reach per ad set + account (frequency 1.8–3.8; ~20% overlap).
        $since = \Carbon\Carbon::parse($yesterday)->subDays(29)->toDateString();
        $sets = [];
        foreach ($rows as $r) {
            if ($r['date'] < $since || empty($r['ad_set'])) {
                continue;
            }
            $sets[$r['ad_set']]['impressions'] = ($sets[$r['ad_set']]['impressions'] ?? 0) + $r['impressions'];
            $sets[$r['ad_set']]['link_clicks'] = ($sets[$r['ad_set']]['link_clicks'] ?? 0) + $r['link_clicks'];
            $sets[$r['ad_set']]['spend'] = ($sets[$r['ad_set']]['spend'] ?? 0) + $r['spend'];
        }
        $reach = [];
        $sumReach = 0;
        foreach ($sets as $id => $x) {
            $freq = 1.8 + (crc32($id) % 200) / 100;
            $rch = (int) ($x['impressions'] / $freq);
            $sumReach += $rch;
            $reach[] = ['date' => $yesterday, 'key' => 'adset:'.$id, 'impressions' => $x['impressions'], 'reach' => $rch, 'link_clicks' => $x['link_clicks'], 'spend' => $x['spend']];
        }
        if ($reach) {
            $reach[] = ['date' => $yesterday, 'key' => 'account', 'impressions' => array_sum(array_column($sets, 'impressions')), 'reach' => (int) ($sumReach * 0.8)];
        }
        $out['reach30'] = $reach;

        return $out;
    }

    public static function remove(Agency $a): void
    {
        foreach (Connection::where('agency_id', $a->id)->where('external_user_id', self::TAG)->get() as $conn) {
            $clientIds = $conn->adAccounts()->pluck('client_id')->filter();
            \App\Models\DailyMetric::whereIn('ad_account_id', $conn->adAccounts()->pluck('id'))->delete();
            \App\Models\LandingPage::where('agency_id', $a->id)->whereIn('client_id', $clientIds)->delete();
            Client::whereIn('id', $clientIds)->delete();
            $conn->delete(); // cascades to ad accounts, structure and metrics
        }
    }
}
