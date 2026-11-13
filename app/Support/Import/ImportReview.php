<?php

namespace App\Support\Import;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Support\ResultResolver;
use Illuminate\Support\Facades\DB;

/** PS-02 / PS-03 (D-296): imported campaigns that still need a client or a goal, with suggestions; nothing changes until saved. */
class ImportReview
{
    public const DAYS = 120;

    /** @return list<array{key: string, network: string, campaign: string, rows: int, spend: float, client_id: ?int, result_type: string, suggest_client: ?int, suggest_goal: ?string}> */
    public static function pending(Agency $agency): array
    {
        $clients = Client::where('agency_id', $agency->id)->where('status', '!=', 'archived')->pluck('name', 'id')->all();
        $groups = DailyMetric::query()->where('agency_id', $agency->id)->where('source', 'import')->whereNotNull('campaign_name')
            ->where('date', '>=', now()->subDays(self::DAYS)->toDateString())
            ->selectRaw('network, campaign_name, client_id, result_type, count(*) as n, sum(spend) as spend')
            ->groupBy('network', 'campaign_name', 'client_id', 'result_type')->get();
        $out = [];
        foreach ($groups as $g) {
            $goal = $g->result_type === 'conversion' ? GoalGuess::fromName($g->campaign_name) : null;
            $suggestClient = $g->client_id ? null : CampaignMatcher::suggest($g->campaign_name, $clients);
            if ($g->client_id && ! $goal) {
                continue; // nothing to decide
            }
            $out[] = [
                'key' => md5($g->network.'|'.$g->campaign_name.'|'.$g->client_id.'|'.$g->result_type),
                'network' => $g->network, 'campaign' => $g->campaign_name, 'rows' => (int) $g->n, 'spend' => (float) $g->spend,
                'client_id' => $g->client_id ? (int) $g->client_id : null, 'result_type' => (string) $g->result_type,
                'suggest_client' => $suggestClient, 'suggest_goal' => $goal,
            ];
        }
        usort($out, fn ($a, $b) => $b['spend'] <=> $a['spend']);

        return $out;
    }

    /**
     * Saves the confirmed choices. Each choice: key => ['client_id' => ?int, 'result_type' => ?string].
     *
     * @param  array<string, array{client_id?: mixed, result_type?: mixed}>  $choices
     * @return array{rows: int, campaigns: int}
     */
    public static function apply(Agency $agency, array $choices): array
    {
        $clientIds = Client::where('agency_id', $agency->id)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $types = array_keys((array) config('results.labels'));
        $rows = $campaigns = 0;
        DB::transaction(function () use ($agency, $choices, $clientIds, $types, &$rows, &$campaigns) {
            foreach (self::pending($agency) as $p) {
                $c = $choices[$p['key']] ?? null;
                if (! $c) {
                    continue;
                }
                $client = filled($c['client_id'] ?? null) && in_array((int) $c['client_id'], $clientIds, true) ? (int) $c['client_id'] : $p['client_id'];
                $type = filled($c['result_type'] ?? null) && in_array($c['result_type'], $types, true) ? $c['result_type'] : $p['result_type'];
                if ($client === $p['client_id'] && $type === $p['result_type']) {
                    continue;
                }
                $q = DailyMetric::query()->where('agency_id', $agency->id)->where('source', 'import')->where('network', $p['network'])
                    ->where('campaign_name', $p['campaign'])->where('result_type', $p['result_type'])
                    ->where('date', '>=', now()->subDays(self::DAYS)->toDateString());
                $p['client_id'] ? $q->where('client_id', $p['client_id']) : $q->whereNull('client_id');
                foreach ($q->get() as $m) {
                    $norm = $type === $p['result_type'] ? [] : ResultResolver::normalizeRow(['result_type' => $type] + $m->only(
                        ['spend', 'impressions', 'reach', 'clicks', 'link_clicks', 'results', 'leads', 'purchases', 'messages', 'calls', 'landing_views', 'add_to_cart', 'checkouts', 'revenue']));
                    $m->forceFill(['client_id' => $client] + array_intersect_key($norm, array_flip(
                        ['result_type', 'roas_eligible', 'results', 'leads', 'purchases', 'messages', 'calls'])))->save();
                    $rows++;
                }
                $campaigns++;
            }
        });
        AuditLog::record('data.import_reviewed', $agency, ['campaigns' => $campaigns, 'rows' => $rows]);

        return ['rows' => $rows, 'campaigns' => $campaigns];
    }
}
