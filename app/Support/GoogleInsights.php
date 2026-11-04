<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

/** Read side for Google depth (#46 search terms, #47 impression share + Quality Score, #48 PMax asset groups). Money in the client's currency. */
class GoogleInsights
{
    protected static function campaignNames(Client $c): array
    {
        return DB::table('campaigns')->join('ad_accounts', 'ad_accounts.id', '=', 'campaigns.ad_account_id')
            ->where('ad_accounts.client_id', $c->id)->selectRaw("campaigns.name as n, ad_accounts.id || ':' || campaigns.external_id as k")->pluck('n', 'k')->all();
    }

    protected static function rate(?string $cur, Client $c, string $date): float
    {
        return Fx::rate((string) ($cur ?: $c->currency), $c->currency, $date);
    }

    /** Average of the daily impression-share values per Search campaign. */
    public static function impressionShare(Client $c, string $from, string $to): array
    {
        $names = static::campaignNames($c);
        $rows = DB::table('google_campaign_daily')->where('agency_id', $c->agency_id)->where('client_id', $c->id)->whereBetween('date', [$from, $to])
            ->selectRaw('ad_account_id, campaign_external, avg(search_is) as search_is, avg(lost_rank) as lost_rank, avg(lost_budget) as lost_budget, avg(top_is) as top_is, avg(abs_top_is) as abs_top_is, count(*) as days')
            ->groupBy('ad_account_id', 'campaign_external')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['name' => $names[$r->ad_account_id.':'.$r->campaign_external] ?? ('Campaign '.$r->campaign_external)] + (array) $r;
        }
        usort($out, fn ($a, $b) => ($b['lost_budget'] + $b['lost_rank']) <=> ($a['lost_budget'] + $a['lost_rank']));

        return $out;
    }

    /** Search terms aggregated over the period. $waste = only terms with spend and no conversions. */
    public static function searchTerms(Client $c, string $from, string $to, bool $waste = false, int $limit = 200): array
    {
        $names = static::campaignNames($c);
        $q = DB::table('search_terms')->where('agency_id', $c->agency_id)->where('client_id', $c->id)->whereBetween('date', [$from, $to])
            ->selectRaw('ad_account_id, campaign_external, term, max(status) as status, max(currency) as currency, sum(impressions) as impressions, sum(clicks) as clicks, sum(cost) as cost, sum(conversions) as conversions, sum(conv_value) as conv_value')
            ->groupBy('ad_account_id', 'campaign_external', 'term');
        if ($waste) {
            $q->havingRaw('sum(conversions) = 0')->havingRaw('sum(clicks) >= ?', [(int) config('insights.search_terms.waste_min_clicks')]);
        }
        $rows = $q->orderByRaw('sum(cost) desc')->limit($limit * 2)->get();
        $min = (float) config('insights.search_terms.waste_min_spend');
        $out = [];
        foreach ($rows as $r) {
            $cost = (float) $r->cost * static::rate($r->currency, $c, $to);
            if ($waste && $cost < $min) {
                continue;
            }
            $out[] = ['key' => $r->ad_account_id.'|'.$r->campaign_external.'|'.$r->term, 'account' => (int) $r->ad_account_id, 'campaign' => $r->campaign_external,
                'campaign_name' => $names[$r->ad_account_id.':'.$r->campaign_external] ?? $r->campaign_external, 'term' => $r->term, 'status' => $r->status,
                'impressions' => (int) $r->impressions, 'clicks' => (int) $r->clicks, 'cost' => $cost, 'conversions' => (float) $r->conversions,
                'cpa' => $r->conversions > 0 ? $cost / $r->conversions : null, 'ctr' => $r->impressions > 0 ? $r->clicks / $r->impressions * 100 : null,
                'waste' => $r->conversions == 0 && $cost >= $min];
        }

        return array_slice($out, 0, $limit);
    }

    public static function wasteTotal(Client $c, string $from, string $to): array
    {
        $t = static::searchTerms($c, $from, $to, true, 1000);

        return ['count' => count($t), 'cost' => array_sum(array_column($t, 'cost'))];
    }

    /** Keyword Quality Score snapshot (last 30 days of metrics) + impression-weighted average. */
    public static function keywords(Client $c, int $limit = 150): array
    {
        $names = static::campaignNames($c);
        $rows = DB::table('google_keywords')->join('ad_accounts', 'ad_accounts.id', '=', 'google_keywords.ad_account_id')
            ->where('google_keywords.agency_id', $c->agency_id)->where('google_keywords.client_id', $c->id)
            ->select('google_keywords.*', 'ad_accounts.currency as acct_currency')->orderByDesc('cost')->limit($limit)->get();
        $list = $rows->map(fn ($r) => (array) $r + ['campaign_name' => $names[$r->ad_account_id.':'.$r->campaign_external] ?? $r->campaign_external,
            'cost_c' => (float) $r->cost * static::rate($r->acct_currency, $c, now()->toDateString())])->all();
        $wImp = array_sum(array_map(fn ($k) => $k['qs'] ? $k['impressions'] : 0, $list));
        $avg = $wImp > 0 ? array_sum(array_map(fn ($k) => $k['qs'] ? $k['qs'] * $k['impressions'] : 0, $list)) / $wImp : null;
        $low = array_values(array_filter($list, fn ($k) => $k['qs'] !== null && $k['qs'] <= 4));

        return ['rows' => $list, 'avg_qs' => $avg, 'low' => count($low), 'snapshot' => $rows->max('snapshot_at')];
    }

    public static function assetGroups(Client $c): array
    {
        return DB::table('google_asset_groups')->join('ad_accounts', 'ad_accounts.id', '=', 'google_asset_groups.ad_account_id')
            ->where('google_asset_groups.agency_id', $c->agency_id)->where('google_asset_groups.client_id', $c->id)
            ->select('google_asset_groups.*', 'ad_accounts.currency as acct_currency')->orderByDesc('cost')->get()
            ->map(function ($r) use ($c) {
                $assets = json_decode((string) $r->assets, true) ?: [];
                $cost = (float) $r->cost * static::rate($r->acct_currency, $c, now()->toDateString());

                return (array) $r + ['cost_c' => $cost, 'cpa' => $r->conversions > 0 ? $cost / $r->conversions : null,
                    'labels' => array_count_values(array_map(fn ($a) => $a['label'] ?: 'UNRATED', $assets)), 'asset_list' => $assets];
            })->all();
    }

    public static function label(?string $v): string
    {
        return ['ABOVE_AVERAGE' => 'Above average', 'AVERAGE' => 'Average', 'BELOW_AVERAGE' => 'Below average', 'EXCELLENT' => 'Excellent', 'GOOD' => 'Good',
            'POOR' => 'Poor', 'PENDING' => 'Pending', 'NO_ADS' => 'No ads', 'BEST' => 'Best', 'LOW' => 'Low', 'LEARNING' => 'Learning', 'UNRATED' => 'Not rated',
            'EXACT' => 'Exact', 'PHRASE' => 'Phrase', 'BROAD' => 'Broad', 'ADDED' => 'Added', 'EXCLUDED' => 'Excluded', 'ADDED_EXCLUDED' => 'Added & excluded', 'NONE' => '—'][$v ?? ''] ?? ($v ? ucwords(strtolower(str_replace('_', ' ', $v))) : '—');
    }
}
