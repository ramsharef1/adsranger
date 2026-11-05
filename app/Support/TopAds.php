<?php

namespace App\Support;

use App\Models\Agency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * #51 Top ads with thumbnails, across the clients a member can see. Each ad keeps its client's currency for display;
 * ranking by spend or cost per result converts to the agency currency first, so clients in JOD and SAR compare fairly.
 */
class TopAds
{
    public const SORTS = ['results', 'cpr', 'ctr', 'spend'];

    public const PERIODS = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'this_month' => 'This month', 'last_month' => 'Last month'];

    /** @return array{0: string, 1: string} from, to */
    public static function range(string $period): array
    {
        return match ($period) {
            'this_month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            '7', '90' => [now()->subDays((int) $period - 1)->toDateString(), now()->toDateString()],
            default => [now()->subDays(29)->toDateString(), now()->toDateString()],
        };
    }

    /**
     * @param  Collection<int, \App\Models\Client>  $clients
     * @return list<array> ad totals + ref, name, thumbnail, client, client_id, currency, network, network_name, spend_base, cpr_base
     */
    public static function list(Agency $agency, Collection $clients, string $from, string $to, ?string $network = null, string $sort = 'results', int $limit = 48): array
    {
        $base = $agency->currency ?: 'USD';
        $today = now()->toDateString();
        $rows = [];
        foreach ($clients as $c) {
            foreach (MetricsQuery::breakdown($c, $from, $to, 'ad') as $ad) {
                if ($ad['spend'] <= 0 && $ad['main_results'] <= 0) {
                    continue;
                }
                $rate = Fx::rate($c->currency, $base, $today);
                $rows[] = $ad + ['client' => $c->name, 'client_id' => $c->id, 'currency' => $c->currency,
                    'spend_base' => $ad['spend'] * $rate, 'cpr_base' => $ad['cpr'] !== null ? $ad['cpr'] * $rate : null];
            }
        }
        $nets = DB::table('ads')->join('ad_accounts', 'ad_accounts.id', '=', 'ads.ad_account_id')
            ->join('ad_networks', 'ad_networks.id', '=', 'ad_accounts.ad_network_id')
            ->where('ads.agency_id', $agency->id)->whereIn('ads.id', array_column($rows, 'ref') ?: [0])
            ->pluck('ad_networks.key', 'ads.id');
        $names = DB::table('ad_networks')->pluck('name', 'key');
        foreach ($rows as &$r) {
            $r['network'] = $nets[$r['ref']] ?? null;
            $r['network_name'] = $r['network'] ? ($names[$r['network']] ?? $r['network']) : null;
        }
        unset($r);
        if ($network) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['network'] === $network));
        }

        usort($rows, match (in_array($sort, self::SORTS, true) ? $sort : 'results') {
            'cpr' => fn ($a, $b) => [$a['cpr_base'] === null, $a['cpr_base'] ?? 0, -$a['spend_base']] <=> [$b['cpr_base'] === null, $b['cpr_base'] ?? 0, -$b['spend_base']],
            'ctr' => fn ($a, $b) => [$b['ctr'] ?? -1, $b['spend_base']] <=> [$a['ctr'] ?? -1, $a['spend_base']],
            'spend' => fn ($a, $b) => $b['spend_base'] <=> $a['spend_base'],
            default => fn ($a, $b) => [$b['main_results'], $b['spend_base']] <=> [$a['main_results'], $a['spend_base']],
        });

        return array_slice($rows, 0, $limit);
    }

    /** Networks that have ads among the given rows, key => name (for the filter). */
    public static function networks(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ($r['network']) {
                $out[$r['network']] = $r['network_name'];
            }
        }
        asort($out);

        return $out;
    }
}
