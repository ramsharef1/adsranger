<?php

namespace App\Audit;

use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Support\Fx;
use App\Support\Insights;
use App\Support\MetricsQuery;
use App\Support\Pacing;
use App\Support\Severity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * #173 Day-1 account audit (FR-016, D-135). Runs the read-only checks on one client's ad accounts and stores up to
 * `audit.max_findings` findings ranked by money at risk (spend in the window on the affected item, client currency).
 * Uses only ad accounts on networks the workspace's plan allows. Thresholds: config/audit.php.
 */
class AccountAudit
{
    public const CHECKS = ['rejected_ads', 'spend_cap', 'no_results', 'tired_ad_set', 'pacing', 'learning_limited', 'default_name'];

    public static function run(Client $client, string $trigger = 'manual', ?User $by = null): Audit
    {
        $agency = $client->agency;
        $tz = $agency?->timezone ?: 'UTC';
        $to = now($tz)->subDay()->startOfDay();
        $from = $to->copy()->subDays(max(1, (int) config('audit.window_days')) - 1);
        $cur = $client->currency ?: ($agency?->currency ?: 'USD');

        $accounts = static::allowedAccounts($client);
        $findings = $accounts->isEmpty() ? [] : static::findings($client, $accounts, $from->toDateString(), $to->toDateString(), $cur);

        usort($findings, fn ($a, $b) => [$b['money'] ?? -1, -Severity::rank($b['severity']), $b['results'] ?? 0]
            <=> [$a['money'] ?? -1, -Severity::rank($a['severity']), $a['results'] ?? 0]);
        $findings = array_slice($findings, 0, (int) config('audit.max_findings'));

        $dismissed = AuditFinding::where('client_id', $client->id)->whereNotNull('dismissed_at')
            ->whereIn('fingerprint', array_column($findings, 'fingerprint'))->get()->keyBy('fingerprint');

        return DB::transaction(function () use ($client, $trigger, $by, $from, $to, $cur, $findings, $dismissed) {
            $audit = Audit::create([
                'agency_id' => $client->agency_id, 'client_id' => $client->id, 'trigger' => $trigger, 'currency' => $cur,
                'money_at_risk' => round(array_sum(array_map(fn ($f) => (float) ($f['money'] ?? 0), $findings)), 2),
                'findings_count' => count($findings), 'window_from' => $from->toDateString(), 'window_to' => $to->toDateString(),
                'ran_by' => $by?->id, 'ran_at' => now(),
            ]);
            foreach (array_values($findings) as $i => $f) {
                $old = $dismissed[$f['fingerprint']] ?? null;
                AuditFinding::create([
                    'agency_id' => $client->agency_id, 'audit_id' => $audit->id, 'client_id' => $client->id, 'rank' => $i + 1,
                    'check_key' => $f['check'], 'severity' => $f['severity'], 'fingerprint' => $f['fingerprint'],
                    'entity_type' => $f['entity_type'], 'entity_id' => $f['entity_id'], 'entity_name' => $f['entity_name'],
                    'network' => $f['network'], 'money_at_risk' => $f['money'] !== null ? round($f['money'], 2) : null,
                    'results_at_risk' => $f['results'], 'params' => $f['params'], 'fix_url' => $f['fix_url'],
                    'dismissed_at' => $old?->dismissed_at, 'dismissed_by' => $old?->dismissed_by,
                ]);
            }

            return $audit;
        });
    }

    /**
     * The client's ad accounts on networks the plan allows (FR-016 point 4): the plan's networks plus the agency's
     * allow / deny overrides. The global on/off switch is not checked here: it controls new connections, not data
     * already synced.
     */
    public static function allowedAccounts(Client $client): Collection
    {
        $agency = $client->agency;
        $allowed = collect($agency->planModel?->networks()->pluck('ad_networks.id') ?? []);
        $overrides = DB::table('agency_network_overrides')->where('agency_id', $agency->id)->get();
        $allowed = $allowed->merge($overrides->where('mode', 'allow')->pluck('ad_network_id'))
            ->diff($overrides->where('mode', 'deny')->pluck('ad_network_id'))->values();

        return $client->adAccounts()->with('network')->get()->filter(fn (AdAccount $a) => $a->network && $allowed->contains($a->ad_network_id))->values();
    }

    /** @return list<array{check:string,severity:string,fingerprint:string,entity_type:?string,entity_id:?int,entity_name:?string,network:?string,money:?float,results:?float,params:array,fix_url:?string}> */
    protected static function findings(Client $client, Collection $accounts, string $from, string $to, string $cur): array
    {
        $accountsById = $accounts->keyBy('id');
        $ids = $accountsById->keys()->all();
        $usd = fn (float $amount) => Fx::convert($amount, 'USD', $cur, $to);

        $setRows = collect(MetricsQuery::breakdown($client, $from, $to, 'ad_set'))->keyBy('ref');
        $sets = AdSet::whereIn('id', $setRows->keys())->whereIn('ad_account_id', $ids)->get()->keyBy('id');
        $setRows = $setRows->filter(fn ($r, $ref) => isset($sets[$ref]));
        $accountSpend = [];
        foreach ($setRows as $ref => $r) {
            $acct = $sets[$ref]->ad_account_id;
            $accountSpend[$acct] = ($accountSpend[$acct] ?? 0) + $r['spend'];
        }

        $out = [];
        $add = function (string $check, string $severity, ?string $type, $entity, ?AdAccount $account, ?float $money, ?float $results, array $params) use (&$out) {
            $out[] = [
                'check' => $check, 'severity' => Severity::normalize($severity),
                'fingerprint' => $check.':'.($type ?? 'client').':'.($entity->id ?? $account?->id ?? 0),
                'entity_type' => $type, 'entity_id' => $entity->id ?? null, 'entity_name' => $entity->name ?? $account?->name,
                'network' => $account?->network?->key, 'money' => $money, 'results' => $results, 'params' => $params,
                'fix_url' => $account ? FixLinks::for($account, $type, $entity) : null,
            ];
        };

        // 1. Rejected ads (#34): the ad set's spend in the window depends on ads that cannot deliver.
        $rejected = Ad::whereIn('ad_account_id', $ids)->whereIn('status', config('audit.rejected_statuses'))->get();
        foreach ($rejected as $ad) {
            $setSpend = $ad->ad_set_id ? (float) ($setRows[$ad->ad_set_id]['spend'] ?? 0) : 0.0;
            $add('rejected_ads', 'critical', 'ad', $ad, $accountsById[$ad->ad_account_id] ?? null, $setSpend, null,
                ['status' => (string) $ad->status, 'ad_set' => $sets[$ad->ad_set_id]->name ?? null]);
        }

        // 2. Account spending limit (#34): from the nightly health check; delivery stops when it is reached.
        foreach ($accounts as $a) {
            foreach ((array) $a->health as $line) {
                if (preg_match('/spending limit (\d+)% used/i', (string) ($line[1] ?? ''), $m) && (int) $m[1] >= (int) config('audit.spend_cap_pct')) {
                    $add('spend_cap', (int) $m[1] >= 100 ? 'critical' : 'warning', 'account', $a, $a, (float) ($accountSpend[$a->id] ?? 0), null, ['pct' => (int) $m[1]]);
                }
            }
        }

        // 3. Spend with no results, and 6. learning-limited estimate (#32).
        $awareness = array_map('strtoupper', config('audit.awareness_goals'));
        $noResultsMin = $usd((float) config('audit.no_results_min_usd'));
        $learningMin = $usd((float) config('audit.learning_min_usd'));
        $learningTarget = (int) config('audit.learning_results');
        foreach ($setRows as $ref => $r) {
            $set = $sets[$ref];
            if (in_array(strtoupper((string) $set->optimization_goal), $awareness, true)) {
                continue;
            }
            $acct = $accountsById[$set->ad_account_id] ?? null;
            if ($r['main_results'] <= 0 && $r['spend'] >= $noResultsMin) {
                $add('no_results', 'warning', 'ad_set', $set, $acct, $r['spend'], null, ['days' => (int) config('audit.window_days')]);
            } elseif ($r['main_results'] > 0 && $r['main_results'] < $learningTarget && $r['spend'] >= $learningMin && static::isActive($set->status)) {
                $add('learning_limited', 'info', 'ad_set', $set, $acct, null, $learningTarget - $r['main_results'],
                    ['results' => (int) round($r['main_results']), 'target' => $learningTarget, 'days' => (int) config('audit.window_days')]);
            }
        }

        // 4. Tired ad sets (#31): high frequency, CTR falling (Insights saturation).
        foreach (Insights::saturation($client)['rows'] as $row) {
            if ($row['state'] === 'ok' || ! isset($sets[$row['ref']])) {
                continue;
            }
            $set = $sets[$row['ref']];
            $add('tired_ad_set', $row['state'] === 'saturating' ? 'warning' : 'info', 'ad_set', $set, $accountsById[$set->ad_account_id] ?? null,
                (float) ($setRows[$row['ref']]['spend'] ?? 0), null,
                ['frequency' => round($row['frequency'], 1), 'ctr_drop' => $row['ctr_drop'] !== null && $row['ctr_drop'] >= 1 ? (int) round($row['ctr_drop']) : null]);
        }

        // 5. Pacing off (#36): month-end forecast away from the budget.
        $p = Pacing::month($client);
        if (in_array($p['status'], ['over', 'under'], true) && $p['budget']) {
            $add('pacing', 'warning', null, null, null, abs($p['forecast_spend'] - $p['budget']), null,
                ['direction' => $p['status'], 'pace' => (int) round($p['pace'] ?? 0)]);
        }

        // 7. Default or copied names (#33), only on items that spent in the window.
        $patterns = config('audit.default_name_patterns');
        $named = fn (?string $n) => $n !== null && collect($patterns)->contains(fn ($re) => preg_match($re, $n));
        $campRows = collect(MetricsQuery::breakdown($client, $from, $to, 'campaign'))->keyBy('ref');
        foreach (Campaign::whereIn('id', $campRows->keys())->whereIn('ad_account_id', $ids)->get() as $c) {
            if ($named($c->name)) {
                $add('default_name', 'info', 'campaign', $c, $accountsById[$c->ad_account_id] ?? null, (float) $campRows[$c->id]['spend'], null, []);
            }
        }
        foreach ($setRows as $ref => $r) {
            if ($named($sets[$ref]->name)) {
                $add('default_name', 'info', 'ad_set', $sets[$ref], $accountsById[$sets[$ref]->ad_account_id] ?? null, (float) $r['spend'], null, []);
            }
        }

        return $out;
    }

    protected static function isActive(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['ACTIVE', 'ENABLED', 'ENABLE', 'RUNNING', ''], true);
    }

    /** Last audit for a client, or null. */
    public static function latest(Client $client): ?Audit
    {
        return Audit::where('client_id', $client->id)->latest('ran_at')->latest('id')->first();
    }
}
