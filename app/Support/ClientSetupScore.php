<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EH-30 (D-297): how complete a client's setup is, as a % on the client list — targets set, data connected or imported,
 * report schedule, a client report link, an SEO site with consent. Facts only; each missing step is named.
 */
class ClientSetupScore
{
    /** @return array{score: int, missing: list<string>} computed once per request for the whole workspace */
    public static function for(Client $c): array
    {
        $key = 'setup-score.'.$c->agency_id;
        $attrs = request()->attributes;
        if (! $attrs->has($key)) {
            $attrs->set($key, self::agency($c->agency));
        }

        return $attrs->get($key)[$c->id] ?? ['score' => 0, 'missing' => []];
    }

    /** @return array<int, array{score: int, missing: list<string>}> */
    public static function agency(Agency $a): array
    {
        $ids = Client::where('agency_id', $a->id)->pluck('id');
        $set = fn (string $table, string $col = 'client_id') => Schema::hasTable($table)
            ? DB::table($table)->whereIn($col, $ids)->distinct()->pluck($col)->map(fn ($i) => (int) $i)->flip()->all() : [];
        $accounts = $set('ad_accounts');
        $imports = DB::table('daily_metrics')->where('agency_id', $a->id)->whereNotNull('client_id')->distinct()->pluck('client_id')->map(fn ($i) => (int) $i)->flip()->all();
        $schedules = $set('report_schedules');
        $links = $set('client_links');
        $seo = Schema::hasTable('seo_sites') && Schema::hasColumn('seo_sites', 'consent_at')
            ? DB::table('seo_sites')->whereIn('client_id', $ids)->whereNotNull('consent_at')->distinct()->pluck('client_id')->map(fn ($i) => (int) $i)->flip()->all() : [];
        $out = [];
        foreach (Client::where('agency_id', $a->id)->get(['id', 'monthly_budget', 'target_cpr', 'target_results', 'target_roas']) as $c) {
            $steps = [
                __('Targets') => $c->monthly_budget || $c->target_cpr || $c->target_results || $c->target_roas,
                __('Data connected or imported') => isset($accounts[$c->id]) || isset($imports[$c->id]),
                __('Report schedule') => isset($schedules[$c->id]),
                __('Client report link') => isset($links[$c->id]),
                __('SEO site') => isset($seo[$c->id]),
            ];
            $done = count(array_filter($steps));
            $out[$c->id] = ['score' => (int) round($done / count($steps) * 100), 'missing' => array_keys(array_filter($steps, fn ($v) => ! $v))];
        }

        return $out;
    }
}
