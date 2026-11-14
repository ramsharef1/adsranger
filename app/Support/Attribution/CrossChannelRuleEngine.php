<?php

namespace App\Support\Attribution;

use App\Models\AutomationRun;
use App\Models\AutomationStrategy;
use App\Models\Client;
use App\Models\ClientUnitEconomic;
use App\Models\DailyMetric;
use App\Models\SkuPerformanceSnapshot;

class CrossChannelRuleEngine
{
    public const ALLOWED_OPS = ['<', '<=', '>', '>=', '==', '!='];

    /**
     * @param  array{
     *   name: string,
     *   conditions: list<array{field: string, op: string, value: float|int|string}>,
     *   actions: list<array<string, mixed>>,
     *   max_budget_delta_pct?: float|int,
     *   execution_mode?: string,
     *   is_template?: bool
     * }  $payload
     */
    public static function createStrategy(Client $client, array $payload): AutomationStrategy
    {
        $maxDelta = min(
            AutomationStrategy::MAX_BUDGET_DELTA_PCT,
            max(0.0, (float) ($payload['max_budget_delta_pct'] ?? 10.0))
        );
        $mode = in_array($payload['execution_mode'] ?? 'dry_run', AutomationStrategy::MODES, true)
            ? (string) ($payload['execution_mode'] ?? 'dry_run')
            : 'dry_run';

        return AutomationStrategy::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'name' => trim((string) $payload['name']),
            'conditions' => array_values($payload['conditions'] ?? []),
            'actions' => self::clampActions(array_values($payload['actions'] ?? []), $maxDelta),
            'max_budget_delta_pct' => $maxDelta,
            'execution_mode' => $mode,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'is_template' => (bool) ($payload['is_template'] ?? false),
        ]);
    }

    /**
     * Collects ad metrics + live store stock + COD return rate for rule evaluation.
     *
     * @return array<string, float|int>
     */
    public static function collectLiveContext(Client $client, int $days = 14): array
    {
        $since = now()->subDays(max(1, $days))->toDateString();

        $totals = DailyMetric::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->where('date', '>=', $since)
            ->selectRaw('COALESCE(SUM(spend),0) as spend, COALESCE(SUM(revenue),0) as revenue, COALESCE(SUM(conversions),0) as conversions, COALESCE(SUM(clicks),0) as clicks, COALESCE(SUM(impressions),0) as impressions')
            ->first();

        $spend = (float) ($totals->spend ?? 0);
        $revenue = (float) ($totals->revenue ?? 0);
        $conversions = (float) ($totals->conversions ?? 0);

        $roas = $spend > 0 ? round($revenue / $spend, 4) : 0.0;
        $cpa = $conversions > 0 ? round($spend / $conversions, 4) : 0.0;

        $econ = ClientUnitEconomic::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->first();

        $observedRates = $econ?->storeRates() ?? ['orders' => 0, 'cod_return_rate_pct' => null];
        $codReturnRate = $observedRates['cod_return_rate_pct'] !== null
            ? (float) $observedRates['cod_return_rate_pct']
            : (float) ($econ?->cod_return_rate_pct ?? 0.0);

        $cogsPct = (float) ($econ?->cogs_pct ?? 0.0);
        $netProfit = $revenue * (1.0 - ($cogsPct / 100.0)) - $spend;
        $poas = $spend > 0 ? round(($revenue * (1.0 - ($cogsPct / 100.0))) / $spend, 4) : 0.0;

        $skus = SkuPerformanceSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->get();

        $minStockQty = $skus->isEmpty() ? 999999 : (int) $skus->min('stock_qty');
        $oosSkuCount = $skus->filter(fn (SkuPerformanceSnapshot $s): bool => (int) $s->stock_qty <= 0 || $s->stock_status === 'out_of_stock')->count();

        return [
            'spend' => round($spend, 3),
            'revenue' => round($revenue, 3),
            'conversions' => round($conversions, 2),
            'roas' => $roas,
            'poas' => $poas,
            'cpa' => $cpa,
            'net_profit' => round($netProfit, 3),
            'cod_return_rate_pct' => round($codReturnRate, 2),
            'min_stock_qty' => $minStockQty,
            'oos_sku_count' => $oosSkuCount,
        ];
    }

    /**
     * Evaluates a strategy without ever mutating external ad spend directly:
     * dry_run logs 'dry_run_logged'; approval_queue logs 'queued_for_approval'.
     *
     * @param  array<string, float|int>|null  $contextOverride
     */
    public static function evaluateStrategy(AutomationStrategy $strategy, ?array $contextOverride = null): AutomationRun
    {
        $client = $strategy->client;
        $metrics = $contextOverride ?? self::collectLiveContext($client);
        $conditions = is_array($strategy->conditions) ? $strategy->conditions : [];
        $maxDelta = min(AutomationStrategy::MAX_BUDGET_DELTA_PCT, max(0.0, (float) $strategy->max_budget_delta_pct));

        $matched = $conditions !== [];
        foreach ($conditions as $cond) {
            $field = (string) ($cond['field'] ?? '');
            $op = (string) ($cond['op'] ?? '==');
            $target = (float) ($cond['value'] ?? 0);
            if (! array_key_exists($field, $metrics) || ! in_array($op, self::ALLOWED_OPS, true)) {
                $matched = false;
                break;
            }
            $actual = (float) $metrics[$field];
            if (! self::compare($actual, $op, $target)) {
                $matched = false;
                break;
            }
        }

        $proposed = $matched ? self::clampActions(is_array($strategy->actions) ? $strategy->actions : [], $maxDelta) : [];
        $mode = in_array($strategy->execution_mode, AutomationStrategy::MODES, true) ? $strategy->execution_mode : 'dry_run';
        $status = ! $matched
            ? 'no_match'
            : ($mode === 'approval_queue' ? 'queued_for_approval' : 'dry_run_logged');

        $now = now();
        $strategy->update(['last_run_at' => $now]);

        return AutomationRun::create([
            'agency_id' => $strategy->agency_id,
            'client_id' => $strategy->client_id,
            'strategy_id' => $strategy->id,
            'execution_mode' => $mode,
            'matched' => $matched,
            'evaluated_metrics' => $metrics,
            'proposed_actions' => $proposed,
            'status' => $status,
            'ran_at' => $now,
        ]);
    }

    public static function cloneTemplateToClient(AutomationStrategy $template, Client $targetClient): AutomationStrategy
    {
        if ((int) $template->agency_id !== (int) $targetClient->agency_id) {
            throw new \InvalidArgumentException('Cross-tenant template cloning is forbidden.');
        }

        return self::createStrategy($targetClient, [
            'name' => $template->name,
            'conditions' => $template->conditions ?? [],
            'actions' => $template->actions ?? [],
            'max_budget_delta_pct' => $template->max_budget_delta_pct,
            'execution_mode' => $template->execution_mode,
            'is_template' => false,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    public static function clampActions(array $actions, float $maxDeltaPct): array
    {
        $cap = min(AutomationStrategy::MAX_BUDGET_DELTA_PCT, max(0.0, $maxDeltaPct));

        return array_map(function (array $action) use ($cap): array {
            if (isset($action['delta_pct'])) {
                $raw = (float) $action['delta_pct'];
                $sign = $raw < 0 ? -1.0 : 1.0;
                $action['delta_pct'] = round($sign * min($cap, abs($raw)), 2);
            }

            return $action;
        }, $actions);
    }

    private static function compare(float $actual, string $op, float $target): bool
    {
        return match ($op) {
            '<' => $actual < $target,
            '<=' => $actual <= $target,
            '>' => $actual > $target,
            '>=' => $actual >= $target,
            '==' => abs($actual - $target) < 0.00001,
            '!=' => abs($actual - $target) >= 0.00001,
            default => false,
        };
    }
}
