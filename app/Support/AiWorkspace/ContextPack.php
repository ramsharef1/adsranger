<?php

namespace App\Support\AiWorkspace;

use App\Models\AiBrainItem;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Support\MetricsQuery;
use Carbon\Carbon;

/**
 * AI Workspace M1 item 2 (spec v1 §11.2): the facts sent with every tool call and checked by Q1, so every AI starts from
 * the same numbers. Last 7 days vs the 7 before, plus the last 30 days, in the client's currency, with source + date,
 * the client's saved rules, and what not to claim. «ما يراه ذكاؤك عن هذا العميل».
 */
class ContextPack
{
    protected const METRICS = ['spend' => 'Spend', 'main_results' => 'Results', 'cpr' => 'Cost per result', 'roas' => 'ROAS', 'ctr' => 'CTR %',
        'impressions' => 'Impressions', 'link_clicks' => 'Link clicks', 'revenue' => 'Revenue'];

    /**
     * @return array{client: string, currency: string, data_until: ?string, periods: array<string, array{from: string, to: string}>,
     *   facts: list<array{label: string, value: float, period: string, source: string, date: ?string}>, rules: list<string>, do_not_claim: list<string>}
     */
    /** FS-02 (D-299): the pack as sent to an AI — only the parts this client allows. @return array<string, mixed> */
    public static function forAi(Client $client, ?Carbon $today = null): array
    {
        return AiScope::apply($client, static::for($client, $today));
    }

    public static function for(Client $client, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $until = DailyMetric::query()->where('agency_id', $client->agency_id)->where('client_id', $client->id)->max('date');
        $until = $until ? Carbon::parse($until)->toDateString() : null;
        $end = $until ? Carbon::parse($until)->min($today->copy()->subDay()) : $today->copy()->subDay();
        $periods = [
            'last_7_days' => ['from' => $end->copy()->subDays(6)->toDateString(), 'to' => $end->toDateString()],
            'previous_7_days' => ['from' => $end->copy()->subDays(13)->toDateString(), 'to' => $end->copy()->subDays(7)->toDateString()],
            'last_30_days' => ['from' => $end->copy()->subDays(29)->toDateString(), 'to' => $end->toDateString()],
        ];

        $facts = [];
        $totals = [];
        foreach ($periods as $name => $p) {
            $totals[$name] = MetricsQuery::totals($client, $p['from'], $p['to']);
            foreach (self::METRICS as $key => $label) {
                $v = $totals[$name][$key] ?? null;
                if ($v !== null) {
                    $facts[] = ['label' => $label, 'value' => round((float) $v, 4), 'period' => $name, 'source' => 'AdsRanger (synced ad networks)', 'date' => $until];
                }
            }
        }
        // Week-on-week changes, so an AI may quote them.
        foreach (['spend', 'main_results', 'cpr', 'roas', 'ctr'] as $key) {
            $now = $totals['last_7_days'][$key] ?? null;
            $before = $totals['previous_7_days'][$key] ?? null;
            if ($now !== null && $before) {
                $facts[] = ['label' => self::METRICS[$key].' change %', 'value' => round(($now - $before) / $before * 100, 1), 'period' => 'last_7_days vs previous_7_days',
                    'source' => 'AdsRanger (computed)', 'date' => $until];
            }
        }

        $rules = AiBrainItem::query()->where('client_id', $client->id)->where('type', 'rule')->latest()->limit(20)->pluck('title')->all();

        return ['client' => $client->name, 'currency' => (string) ($client->currency ?: 'USD'), 'data_until' => $until, 'periods' => $periods,
            'facts' => $facts, 'rules' => $rules,
            'do_not_claim' => ['numbers that are not in these facts', 'guaranteed results or rankings', 'competitor tool names', 'results for other clients']];
    }
}
