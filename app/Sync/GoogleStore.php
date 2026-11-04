<?php

namespace App\Sync;

use App\Models\AdAccount;
use App\Support\LandingPages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Stores the Google-only extras a pull returns: impression share, search terms, keyword QS, PMax asset groups, landing URLs. */
class GoogleStore
{
    public static function store(AdAccount $a, array $g, string $from, string $to): void
    {
        $base = ['agency_id' => $a->agency_id, 'client_id' => $a->client_id, 'ad_account_id' => $a->id];

        if (isset($g['campaign_daily'])) {
            DB::table('google_campaign_daily')->where('ad_account_id', $a->id)->whereBetween('date', [$from, $to])->delete();
            foreach (array_chunk($g['campaign_daily'], 500) as $chunk) {
                DB::table('google_campaign_daily')->insert(array_map(fn ($r) => $base + [
                    'campaign_external' => (string) $r['campaign'], 'date' => $r['date'],
                    'search_is' => $r['search_is'] ?? null, 'lost_rank' => $r['lost_rank'] ?? null, 'lost_budget' => $r['lost_budget'] ?? null,
                    'top_is' => $r['top_is'] ?? null, 'abs_top_is' => $r['abs_top_is'] ?? null], $chunk));
            }
        }

        if (isset($g['search_terms'])) {
            DB::table('search_terms')->where('ad_account_id', $a->id)->whereBetween('date', [$from, $to])->delete();
            foreach (array_chunk($g['search_terms'], 500) as $chunk) {
                DB::table('search_terms')->insert(array_map(fn ($r) => $base + [
                    'campaign_external' => (string) $r['campaign'], 'ad_group_external' => isset($r['ad_group']) ? (string) $r['ad_group'] : null,
                    'term' => Str::limit((string) $r['term'], 298, ''), 'status' => $r['status'] ?? null, 'date' => $r['date'],
                    'impressions' => (int) ($r['impressions'] ?? 0), 'clicks' => (int) ($r['clicks'] ?? 0), 'cost' => (float) ($r['cost'] ?? 0),
                    'conversions' => (float) ($r['conversions'] ?? 0), 'conv_value' => (float) ($r['conv_value'] ?? 0), 'currency' => $a->currency], $chunk));
            }
        }

        if (isset($g['keywords'])) {
            DB::table('google_keywords')->where('ad_account_id', $a->id)->delete();
            $now = now();
            foreach (array_chunk($g['keywords'], 500) as $chunk) {
                DB::table('google_keywords')->insert(array_map(fn ($r) => $base + [
                    'campaign_external' => (string) $r['campaign'], 'ad_group_external' => (string) $r['ad_group'], 'criterion_id' => (string) $r['id'],
                    'text' => Str::limit((string) $r['text'], 298, ''), 'match_type' => $r['match_type'] ?? null, 'status' => $r['status'] ?? null,
                    'qs' => $r['qs'] ?? null, 'creative_qs' => $r['creative_qs'] ?? null, 'landing_qs' => $r['landing_qs'] ?? null, 'ctr_qs' => $r['ctr_qs'] ?? null,
                    'impressions' => (int) ($r['impressions'] ?? 0), 'clicks' => (int) ($r['clicks'] ?? 0), 'cost' => (float) ($r['cost'] ?? 0),
                    'conversions' => (float) ($r['conversions'] ?? 0), 'snapshot_at' => $now], $chunk));
            }
        }

        if (isset($g['asset_groups'])) {
            DB::table('google_asset_groups')->where('ad_account_id', $a->id)->delete();
            $now = now();
            foreach ($g['asset_groups'] as $r) {
                DB::table('google_asset_groups')->insert($base + [
                    'campaign_external' => (string) $r['campaign'], 'campaign_name' => $r['campaign_name'] ?? null, 'asset_group_id' => (string) $r['id'],
                    'name' => $r['name'] ?? null, 'status' => $r['status'] ?? null, 'ad_strength' => $r['ad_strength'] ?? null,
                    'impressions' => (int) ($r['impressions'] ?? 0), 'clicks' => (int) ($r['clicks'] ?? 0), 'cost' => (float) ($r['cost'] ?? 0),
                    'conversions' => (float) ($r['conversions'] ?? 0), 'conv_value' => (float) ($r['conv_value'] ?? 0),
                    'assets' => json_encode(array_slice($r['assets'] ?? [], 0, 80)), 'snapshot_at' => $now]);
            }
        }

        if (! empty($g['landing_urls'])) {
            LandingPages::register($a, $g['landing_urls'], 'google');
        }
    }
}
