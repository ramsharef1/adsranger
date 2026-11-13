<?php

namespace App\Alerts;

use App\Mail\AlertMail;
use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\LandingPage;
use App\Support\CriticalPush;
use App\Support\Fx;
use App\Support\GoogleInsights;
use App\Support\MetricsQuery;
use App\Support\OnCall;
use App\Support\Pacing;
use App\Support\PlanGate;
use App\Support\Severity;
use App\Support\Webhooks;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/** Evaluates the alert rules for one agency and keeps the inbox in sync (open, refresh, auto-resolve). */
class AlertEngine
{
    /** @var array<int, int> agency id → on-call user id for this run (0 = nobody) */
    protected static array $onCall = [];

    /** Effective rules for an agency: [key => ['enabled'=>bool,'locked'=>bool,'severity'=>..,'t'=>thresholds,'label'=>..]]. A rule above the plan is locked and never runs (#23, D-30). */
    public static function rules(Agency $a): array
    {
        $over = AlertRule::where('agency_id', $a->id)->get()->keyBy('rule_key');
        $out = [];
        foreach (config('alerts.rules') as $k => $r) {
            $o = $over[$k] ?? null;
            $locked = ! PlanGate::allowsAlertRule($a, $k);
            $out[$k] = ['enabled' => ! $locked && ($o ? $o->enabled : true), 'locked' => $locked, 'severity' => $r['severity'], 'label' => $r['label'],
                't' => array_merge($r['thresholds'], array_filter((array) ($o?->thresholds ?? []), fn ($v) => $v !== null && $v !== ''))];
        }

        return $out;
    }

    /** @return array{opened:int,refreshed:int,resolved:int} */
    public static function run(Agency $a, string $day): array
    {
        static::$onCall = [];
        $rules = static::rules($a);
        $found = [];
        $d = Carbon::parse($day);

        foreach (Client::where('agency_id', $a->id)->where('status', 'active')->get() as $c) {
            if ($rules['tracking_break']['enabled']) {
                static::trackingBreak($c, $d, $rules['tracking_break'], $found);
            }
            if ($rules['spend_no_results']['enabled']) {
                static::spendNoResults($c, $d, $rules['spend_no_results'], $found);
            }
            if ($rules['cpr_rising']['enabled']) {
                static::cprRising($c, $d, $rules['cpr_rising'], $found);
            }
            if ($rules['pacing']['enabled'] ?? false) {
                static::pacing($c, $d, $rules['pacing'], $found);
            }
            if ($rules['search_term_waste']['enabled'] ?? false) {
                static::searchTermWaste($c, $d, $rules['search_term_waste'], $found);
            }
            if ($rules['ctr_drop']['enabled'] ?? false) {
                static::ctrDrop($c, $d, $rules['ctr_drop'], $found);
            }
            if ($rules['roas_drop']['enabled'] ?? false) {
                static::roasDrop($c, $d, $rules['roas_drop'], $found);
            }
            if ($rules['spend_spike']['enabled'] ?? false) {
                static::spendSpike($c, $d, $rules['spend_spike'], $found);
            }
            if ($rules['high_frequency']['enabled'] ?? false) {
                static::highFrequency($c, $d, $rules['high_frequency'], $found);
            }
            if ($rules['lead_cost_spike']['enabled'] ?? false) {
                static::leadCostSpike($c, $d, $rules['lead_cost_spike'], $found);
            }
            if ($rules['high_cpc']['enabled'] ?? false) {
                static::highCpc($c, $d, $rules['high_cpc'], $found);
            }
            if ($rules['fx_drift']['enabled'] ?? false) {
                static::fxDrift($c, $d, $rules['fx_drift'], $found);
            }
        }
        if ($rules['zero_impressions']['enabled'] ?? false) {
            static::zeroImpressions($a, $d, $rules['zero_impressions'], $found);
        }
        if ($rules['tracking_issue']['enabled']) {
            static::trackingIssue($a, $d, $rules['tracking_issue'], $found);
        }
        if ($rules['under_delivery']['enabled']) {
            static::underDelivery($a, $d, $rules['under_delivery'], $found);
        }
        if ($rules['ending_soon']['enabled']) {
            static::endingSoon($a, $d, $rules['ending_soon'], $found);
        }
        if ($rules['connection_problem']['enabled']) {
            static::connectionProblem($a, $rules['connection_problem'], $found);
        }
        if ($rules['account_health']['enabled']) {
            static::accountHealth($a, $rules['account_health'], $found);
        }
        if ($rules['landing_page']['enabled'] ?? false) {
            static::landingPage($a, $rules['landing_page'], $found);
        }
        if ($rules['upload_stale']['enabled'] ?? false) {
            static::uploadStale($a, $rules['upload_stale'], $found);
        }

        return static::store($a, $found);
    }

    protected static function add(array &$found, string $rule, array $r, array $data): void
    {
        $fp = $rule.':'.($data['entity_type'] ?? 'x').':'.($data['entity_id'] ?? 0);
        $found[$fp] = $data + ['rule_key' => $rule, 'severity' => $r['severity'], 'fingerprint' => $fp];
    }

    protected static function trackingBreak(Client $c, Carbon $d, array $r, array &$found): void
    {
        $n = (int) $r['t']['min_days'];
        $days = MetricsQuery::daily($c, $d->copy()->subDays($n)->toDateString(), $d->toDateString());
        $y = $days[$d->toDateString()] ?? null;
        if (! $y || $y['spend'] <= 0 || $y['main_results'] > 0) {
            return;
        }
        for ($i = 1; $i <= $n; $i++) {
            if (($days[$d->copy()->subDays($i)->toDateString()]['main_results'] ?? 0) <= 0) {
                return;
            }
        }
        static::add($found, 'tracking_break', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
            'title' => 'Tracking break: '.$c->name, 'detail' => "0 results on {$d->toDateString()} after results on each of the previous $n days. Check the pixel / conversion tags.",
            'payload' => ['spend' => round($y['spend'], 2)]]);
    }

    protected static function spendNoResults(Client $c, Carbon $d, array $r, array &$found): void
    {
        $days = (int) $r['t']['days'];
        $t = MetricsQuery::totals($c, $d->copy()->subDays($days - 1)->toDateString(), $d->toDateString());
        if ($t['spend'] >= (float) $r['t']['min_spend'] && array_sum($t['by_type']) <= 0) {
            static::add($found, 'spend_no_results', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'Spend with no results: '.$c->name,
                'detail' => number_format($t['spend'], 2).' '.$c->currency." spent in the last $days days with 0 results.", 'payload' => ['spend' => round($t['spend'], 2)]]);
        }
    }

    protected static function cprRising(Client $c, Carbon $d, array $r, array &$found): void
    {
        $now = MetricsQuery::totals($c, $d->copy()->subDays(6)->toDateString(), $d->toDateString());
        $prev = MetricsQuery::totals($c, $d->copy()->subDays(13)->toDateString(), $d->copy()->subDays(7)->toDateString());
        $min = (float) ($r['t']['min_results'] ?? 5);
        if (! $now['cpr'] || ! $prev['cpr'] || $now['main_results'] < $min || $prev['main_results'] < $min || $now['main_type'] !== $prev['main_type']) {
            return;
        }

        // M5.2: Minimum 7-day spend floor (>= $50 USD-equivalent OR >= 3x Target CPR) so tiny test budgets never trigger false alarms.
        $minSpendUsd = (float) ($r['t']['min_spend_usd'] ?? 50);
        $targetMult = (float) ($r['t']['target_cpr_multiplier'] ?? 3);
        $spendUsd = Fx::convert((float) $now['spend'], $c->currency ?: 'USD', 'USD', $d->toDateString());
        $meetsUsdFloor = $spendUsd >= $minSpendUsd;
        $meetsTargetFloor = ((float) ($c->target_cpr ?? 0) > 0) && ((float) $now['spend'] >= ($targetMult * (float) $c->target_cpr));
        if (! $meetsUsdFloor && ! $meetsTargetFloor) {
            return;
        }

        $pct = ($now['cpr'] / $prev['cpr'] - 1) * 100;
        $warnPct = (float) ($r['t']['pct'] ?? 20);
        $critPct = (float) ($r['t']['critical_pct'] ?? 40);

        if ($pct > $warnPct) {
            $effectiveRule = $r;
            $effectiveRule['severity'] = $pct > $critPct ? 'critical' : 'warning';
            static::add($found, 'cpr_rising', $effectiveRule, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'Cost per result up '.round($pct).'%: '.$c->name,
                'detail' => 'Last 7 days '.number_format($now['cpr'], 2).' vs '.number_format($prev['cpr'], 2).' '.$c->currency.' the week before.',
                'payload' => ['now' => round($now['cpr'], 4), 'prev' => round($prev['cpr'], 4), 'pct' => round($pct, 1), 'spend_usd' => round($spendUsd, 2)]]);
        }
    }

    /** Month-end forecast too far from the monthly budget (#36). $d = the last complete day. */
    protected static function pacing(Client $c, Carbon $d, array $r, array &$found): void
    {
        $p = Pacing::month($c, $d->copy()->addDay());
        if (! $p['budget'] || $p['full_days'] < (int) $r['t']['from_day'] || $p['forecast_vs_budget'] === null) {
            return;
        }
        $off = $p['forecast_vs_budget'] - 100;
        if (abs($off) < (float) $r['t']['pct']) {
            return;
        }
        $over = $off > 0;
        static::add($found, 'pacing', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
            'title' => ($over ? 'Over-pacing ' : 'Under-pacing ').round(abs($off)).'%: '.$c->name,
            'detail' => 'Forecast '.number_format($p['forecast_spend'], 2).' vs budget '.number_format($p['budget'], 2).' '.$c->currency.' for '.$p['month_label']
                .'. Spend about '.number_format((float) $p['daily_needed'], 2).' a day to land on budget.',
            'payload' => ['forecast' => round($p['forecast_spend'], 2), 'budget' => $p['budget'], 'pct' => round($off, 1)]]);
    }

    /** Search terms with spend and no conversions over the last 7 days (#46). */
    protected static function searchTermWaste(Client $c, Carbon $d, array $r, array &$found): void
    {
        $w = GoogleInsights::wasteTotal($c, $d->copy()->subDays(6)->toDateString(), $d->toDateString());
        if ($w['count'] && $w['cost'] >= (float) $r['t']['min_spend']) {
            static::add($found, 'search_term_waste', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'Wasted search terms: '.$c->name,
                'detail' => number_format($w['cost'], 2).' '.$c->currency.' on '.$w['count'].' search terms with no conversions in the last 7 days. Review them in Google Ads → Search terms.',
                'payload' => ['cost' => round($w['cost'], 2), 'terms' => $w['count']]]);
        }
    }

    /** Broken landing pages that ads still send clicks to (#49). */
    protected static function landingPage(Agency $a, array $r, array &$found): void
    {
        // DB-01: broken pages, and redirects that land on another site; "down since" comes from the first failed check.
        $q = LandingPage::where('agency_id', $a->id)->where('clicks_30d', '>=', (int) $r['t']['min_clicks'])->where('last_seen_at', '>=', now()->subDays(30));
        $q = Schema::hasColumn('landing_pages', 'down_since') ? $q->where(fn ($w) => $w->whereNotNull('down_since')->orWhere('state', 'broken')) : $q->where('state', 'broken');
        foreach ($q->limit(50)->get() as $lp) {
            $offsite = $lp->state !== 'broken';
            static::add($found, 'landing_page', $r, ['client_id' => $lp->client_id, 'entity_type' => 'landing_page', 'entity_id' => $lp->id,
                'entity_name' => mb_substr($lp->url, 0, 190), 'title' => 'Broken landing page',
                'detail' => $lp->url.' — '.($offsite ? 'redirects to another site: '.mb_substr((string) $lp->final_url, 0, 200) : ($lp->error ?: 'HTTP '.$lp->status_code)).'.'
                    .($lp->down_since ? ' Down since '.$lp->down_since->format('Y-m-d H:i').' UTC.' : '').' Ads sent '.$lp->clicks_30d.' clicks here in 30 days.',
                'payload' => ['status' => $lp->status_code, 'down_since' => $lp->down_since?->toIso8601String(), 'offsite' => $offsite]]);
        }
    }

    /**
     * FS-17 (R4, D-304): the client works in local money (budget, reports) but an ad account spends in another currency;
     * the rate has moved by the threshold or more since the 1st of the month. Resolves itself when the move shrinks or the month turns.
     */
    protected static function fxDrift(Client $c, Carbon $d, array $r, array &$found): void
    {
        $local = strtoupper((string) ($c->currency ?: 'USD'));
        $spendIn = DailyMetric::query()->where('client_id', $c->id)->where('date', '>=', $d->copy()->subDays(30)->toDateString())
            ->where('spend', '>', 0)->whereNotNull('currency')->distinct()->pluck('currency')->map(fn ($x) => strtoupper($x))->reject(fn ($x) => $x === $local)->values();
        if ($spendIn->isEmpty()) {
            return;
        }
        $first = $d->copy()->startOfMonth()->toDateString();
        $pct = (float) $r['t']['pct'];
        foreach ($spendIn as $cur) {
            $a = Fx::usdTo($cur, $first);
            $b = Fx::usdTo($local, $first);
            $a2 = Fx::usdTo($cur, $d->toDateString());
            $b2 = Fx::usdTo($local, $d->toDateString());
            if (! $a || ! $b || ! $a2 || ! $b2) {
                continue; // no stored rates: say nothing rather than guess
            }
            $then = $b / $a;   // 1 unit of spend currency in local money on the 1st
            $now = $b2 / $a2;  // … today
            $move = ($now / $then - 1) * 100;
            if (abs($move) < $pct) {
                continue;
            }
            $budget = (float) ($c->monthly_budget ?? 0);
            static::add($found, 'fx_drift', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'Exchange rate moved '.round(abs($move)).'%: '.$c->name,
                'detail' => '1 '.$cur.' = '.number_format($now, 2).' '.$local.' now, '.number_format($then, 2).' '.$local.' on '.$first.'.'
                    .($budget > 0 ? ' The monthly budget of '.number_format($budget, 2).' '.$local.' now covers '.number_format($budget / $now, 2).' '.$cur.' of spend instead of '.number_format($budget / $then, 2).' '.$cur.'.' : ''),
                'payload' => ['spend_currency' => $cur, 'local' => $local, 'then' => round($then, 6), 'now' => round($now, 6), 'move_pct' => round($move, 1), 'since' => $first]]);
        }
    }

    /** PS-08: clients fed by uploads only (no account synced in 3 days) whose newest import is older than the threshold. */
    protected static function uploadStale(Agency $a, array $r, array &$found): void
    {
        $days = max(1, (int) $r['t']['days']);
        $live = AdAccount::where('agency_id', $a->id)->whereNotNull('client_id')->where('last_synced_at', '>=', now()->subDays(3))->pluck('client_id')->all();
        $last = DailyMetric::query()->where('agency_id', $a->id)->where('source', 'import')->whereNotNull('client_id')
            ->selectRaw('client_id, max(created_at) as at')->groupBy('client_id')->pluck('at', 'client_id');
        foreach (Client::where('agency_id', $a->id)->where('status', 'active')->whereIn('id', $last->keys())->whereNotIn('id', $live)->get() as $c) {
            $age = (int) floor(Carbon::parse($last[$c->id])->diffInHours(now()) / 24);
            if ($age >= $days) {
                static::add($found, 'upload_stale', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                    'title' => 'No fresh data: '.$c->name,
                    'detail' => "Last upload $age days ago. Export the latest report from the ad platform and import it.",
                    'payload' => ['days' => $age, 'last_upload' => Carbon::parse($last[$c->id])->toIso8601String()]]);
            }
        }
    }

    protected static function trackingIssue(Agency $a, Carbon $d, array $r, array &$found): void
    {
        $days = (int) $r['t']['days'];
        $from = $d->copy()->subDays($days - 1)->toDateString();
        $rows = DailyMetric::query()->where('agency_id', $a->id)->whereNotNull('ad_set_ref')->whereBetween('date', [$from, $d->toDateString()])
            ->selectRaw('ad_set_ref, client_id, ad_account_id, currency, SUM(spend) as spend, SUM(results) as results')
            ->groupBy('ad_set_ref', 'client_id', 'ad_account_id', 'currency')->get();
        $sets = AdSet::whereIn('id', $rows->pluck('ad_set_ref'))->whereIn('optimization_goal', config('alerts.conversion_goals'))->get()->keyBy('id');
        $clients = Client::whereIn('id', $rows->pluck('client_id')->filter())->get()->keyBy('id');
        foreach ($rows as $row) {
            $s = $sets[$row->ad_set_ref] ?? null;
            if (! $s || $row->results > 0) {
                continue;
            }
            $cur = $clients[$row->client_id]->currency ?? $row->currency;
            $spend = Fx::convert((float) $row->spend, $row->currency, $cur, $d->toDateString());
            if ($spend >= (float) $r['t']['min_spend']) {
                static::add($found, 'tracking_issue', $r, ['client_id' => $row->client_id, 'ad_account_id' => $row->ad_account_id, 'entity_type' => 'ad_set',
                    'entity_id' => $s->id, 'entity_name' => $s->name, 'title' => 'Tracking issue: '.$s->name,
                    'detail' => number_format($spend, 2).' '.$cur." in $days days on a {$s->optimization_goal} goal with 0 results.", 'payload' => ['spend' => round($spend, 2)]]);
            }
        }
    }

    protected static function underDelivery(Agency $a, Carbon $d, array $r, array &$found): void
    {
        $active = ['ACTIVE', 'ENABLED'];
        $spent = DailyMetric::query()->where('agency_id', $a->id)->where('date', $d->toDateString())
            ->selectRaw('ad_set_ref, campaign_ref, SUM(spend) as spend')->groupBy('ad_set_ref', 'campaign_ref')->get();
        $bySet = $spent->whereNotNull('ad_set_ref')->groupBy('ad_set_ref')->map->sum('spend');
        $byCamp = $spent->groupBy('campaign_ref')->map->sum('spend');
        $check = function ($m, string $type, float $spend) use ($r, &$found, $d) {
            if (! $m->daily_budget || $m->daily_budget <= 0 || ($m->start_time && $m->start_time->gt($d))) {
                return;
            }
            $pct = $spend / $m->daily_budget * 100;
            if ($pct < (float) $r['t']['pct']) {
                $acct = AdAccount::find($m->ad_account_id);
                static::add($found, 'under_delivery', $r, ['client_id' => $acct?->client_id, 'ad_account_id' => $m->ad_account_id, 'entity_type' => $type,
                    'entity_id' => $m->id, 'entity_name' => $m->name, 'title' => 'Under-delivery: '.$m->name,
                    'detail' => 'Spent '.number_format($spend, 2).' of a '.number_format($m->daily_budget, 2).' daily budget ('.round($pct).'%) on '.$d->toDateString().'.',
                    'payload' => ['spend' => round($spend, 2), 'budget' => (float) $m->daily_budget]]);
            }
        };
        foreach (AdSet::where('agency_id', $a->id)->whereIn('status', $active)->whereNotNull('daily_budget')->get() as $s) {
            $check($s, 'ad_set', (float) ($bySet[$s->id] ?? 0));
        }
        foreach (Campaign::where('agency_id', $a->id)->whereIn('status', $active)->whereNotNull('daily_budget')->get() as $c) {
            $check($c, 'campaign', (float) ($byCamp[$c->id] ?? 0));
        }
    }

    protected static function endingSoon(Agency $a, Carbon $d, array $r, array &$found): void
    {
        $until = now()->addDays((int) $r['t']['days']);
        foreach ([[Campaign::class, 'campaign'], [AdSet::class, 'ad_set']] as [$cls, $type]) {
            foreach ($cls::where('agency_id', $a->id)->whereIn('status', ['ACTIVE', 'ENABLED'])->whereBetween('end_time', [now(), $until])->get() as $m) {
                $acct = AdAccount::find($m->ad_account_id);
                static::add($found, 'ending_soon', $r, ['client_id' => $acct?->client_id, 'ad_account_id' => $m->ad_account_id, 'entity_type' => $type,
                    'entity_id' => $m->id, 'entity_name' => $m->name, 'title' => 'Ending soon: '.$m->name,
                    'detail' => ucfirst(str_replace('_', ' ', $type)).' ends '.$m->end_time->diffForHumans().' ('.$m->end_time->toDateString().').']);
            }
        }
    }

    protected static function connectionProblem(Agency $a, array $r, array &$found): void
    {
        foreach (AdAccount::with(['connection', 'network'])->where('agency_id', $a->id)->where('is_active', true)->get() as $acct) {
            $c = $acct->connection;
            $why = null;
            if ($c && $c->status === 'error') {
                $why = 'The connection needs attention: '.($c->last_error ?: 'error');
            } elseif ($c && $c->token_expires_at && $c->token_expires_at->isPast() && ! $c->refresh_token) {
                $why = 'The access token expired on '.$c->token_expires_at->toDateString().'. Reconnect.';
            } elseif ($acct->sync_failures >= (int) $r['t']['failures']) {
                $why = 'Sync failed '.$acct->sync_failures.' times in a row: '.($acct->sync_error ?: 'unknown error');
            }
            if ($why) {
                static::add($found, 'connection_problem', $r, ['client_id' => $acct->client_id, 'ad_account_id' => $acct->id, 'entity_type' => 'ad_account',
                    'entity_id' => $acct->id, 'entity_name' => $acct->name, 'title' => 'Connection problem: '.($acct->network?->name).' · '.$acct->name, 'detail' => $why]);
            }
        }
    }

    protected static function accountHealth(Agency $a, array $r, array &$found): void
    {
        foreach (AdAccount::with('network')->where('agency_id', $a->id)->where('is_active', true)->whereNotNull('health')->get() as $acct) {
            foreach ((array) $acct->health as $i => $issue) {
                [$sev, $msg] = array_pad((array) $issue, 2, '');
                if (str_contains($msg, 'rejected') && (int) preg_replace('/\D.*/', '', $msg) < (int) $r['t']['min_rejected']) {
                    continue;
                }
                static::add($found, 'account_health', ['severity' => Severity::normalize($sev) === 'critical' ? 'critical' : 'warning'] + $r, ['client_id' => $acct->client_id, 'ad_account_id' => $acct->id,
                    'entity_type' => 'ad_account', 'entity_id' => $acct->id * 100 + $i, 'entity_name' => $acct->name,
                    'title' => 'Account health: '.($acct->network?->name).' · '.$acct->name, 'detail' => $msg]);
            }
        }
    }

    protected static function ctrDrop(Client $c, Carbon $d, array $r, array &$found): void
    {
        $now = MetricsQuery::totals($c, $d->copy()->subDays(6)->toDateString(), $d->toDateString());
        $prev = MetricsQuery::totals($c, $d->copy()->subDays(13)->toDateString(), $d->copy()->subDays(7)->toDateString());
        $minImp = (float) ($r['t']['min_impressions'] ?? 1000);
        if (! $now['ctr'] || ! $prev['ctr'] || $now['impressions'] < $minImp || $prev['impressions'] < $minImp) {
            return;
        }
        $dropPct = (1 - $now['ctr'] / $prev['ctr']) * 100;
        if ($dropPct >= (float) $r['t']['pct']) {
            static::add($found, 'ctr_drop', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'CTR down '.round($dropPct).'%: '.$c->name,
                'detail' => 'Last 7 days '.number_format($now['ctr'], 2).'% vs '.number_format($prev['ctr'], 2).'% the week before.',
                'payload' => ['now' => round($now['ctr'], 3), 'prev' => round($prev['ctr'], 3), 'pct' => round($dropPct, 1)]]);
        }
    }

    protected static function roasDrop(Client $c, Carbon $d, array $r, array &$found): void
    {
        $now = MetricsQuery::totals($c, $d->copy()->subDays(6)->toDateString(), $d->toDateString());
        $prev = MetricsQuery::totals($c, $d->copy()->subDays(13)->toDateString(), $d->copy()->subDays(7)->toDateString());
        $minSpend = (float) ($r['t']['min_spend'] ?? 50);
        if (! $now['roas'] || ! $prev['roas'] || $now['roas_spend'] < $minSpend || $prev['roas_spend'] < $minSpend) {
            return;
        }
        $dropPct = (1 - $now['roas'] / $prev['roas']) * 100;
        if ($dropPct >= (float) $r['t']['pct']) {
            static::add($found, 'roas_drop', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'ROAS down '.round($dropPct).'%: '.$c->name,
                'detail' => 'Last 7 days '.number_format($now['roas'], 2).'x vs '.number_format($prev['roas'], 2).'x the week before.',
                'payload' => ['now' => round($now['roas'], 2), 'prev' => round($prev['roas'], 2), 'pct' => round($dropPct, 1)]]);
        }
    }

    protected static function spendSpike(Client $c, Carbon $d, array $r, array &$found): void
    {
        $days = MetricsQuery::daily($c, $d->copy()->subDays(7)->toDateString(), $d->toDateString());
        $y = $days[$d->toDateString()] ?? ['spend' => 0.0];
        $prevSum = 0.0;
        for ($i = 1; $i <= 7; $i++) {
            $prevSum += $days[$d->copy()->subDays($i)->toDateString()]['spend'] ?? 0.0;
        }
        $avg = $prevSum / 7;
        if ($y['spend'] < (float) ($r['t']['min_spend'] ?? 50) || $avg <= 0) {
            return;
        }
        $spikePct = ($y['spend'] / $avg - 1) * 100;
        if ($spikePct >= (float) $r['t']['pct']) {
            static::add($found, 'spend_spike', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'Daily spend spike +'.round($spikePct).'%: '.$c->name,
                'detail' => 'Spent '.number_format($y['spend'], 2).' '.$c->currency.' on '.$d->toDateString().' vs '.number_format($avg, 2).' daily average.',
                'payload' => ['spend' => round($y['spend'], 2), 'avg' => round($avg, 2), 'pct' => round($spikePct, 1)]]);
        }
    }

    protected static function highFrequency(Client $c, Carbon $d, array $r, array &$found): void
    {
        $now = MetricsQuery::totals($c, $d->copy()->subDays(6)->toDateString(), $d->toDateString());
        if ($now['reach'] <= 0 || $now['impressions'] < 1000) {
            return;
        }
        $freq = $now['impressions'] / $now['reach'];
        if ($freq >= (float) ($r['t']['max_freq'] ?? 4)) {
            static::add($found, 'high_frequency', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'High ad frequency ('.number_format($freq, 1).'): '.$c->name,
                'detail' => 'Reached people saw ads '.number_format($freq, 1).' times on average over the last 7 days. Refresh creatives or widen audiences.',
                'payload' => ['frequency' => round($freq, 2)]]);
        }
    }

    protected static function leadCostSpike(Client $c, Carbon $d, array $r, array &$found): void
    {
        if (! $c->target_cpr || $c->target_cpr <= 0) {
            return;
        }
        $now = MetricsQuery::totals($c, $d->copy()->subDays(6)->toDateString(), $d->toDateString());
        if (! $now['cpr'] || $now['main_results'] < 3) {
            return;
        }
        $overPct = ($now['cpr'] / (float) $c->target_cpr - 1) * 100;
        if ($overPct >= (float) $r['t']['pct']) {
            static::add($found, 'lead_cost_spike', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'Cost per result '.round($overPct).'% above target: '.$c->name,
                'detail' => 'Last 7 days '.number_format($now['cpr'], 2).' vs target '.number_format((float) $c->target_cpr, 2).' '.$c->currency.'.',
                'payload' => ['cpr' => round($now['cpr'], 2), 'target' => (float) $c->target_cpr, 'pct' => round($overPct, 1)]]);
        }
    }

    protected static function highCpc(Client $c, Carbon $d, array $r, array &$found): void
    {
        $now = MetricsQuery::totals($c, $d->copy()->subDays(6)->toDateString(), $d->toDateString());
        $prev = MetricsQuery::totals($c, $d->copy()->subDays(13)->toDateString(), $d->copy()->subDays(7)->toDateString());
        $minClicks = (float) ($r['t']['min_clicks'] ?? 20);
        if ($now['link_clicks'] < $minClicks || $prev['link_clicks'] < $minClicks || $now['spend'] <= 0 || $prev['spend'] <= 0) {
            return;
        }
        $cpcNow = $now['spend'] / $now['link_clicks'];
        $cpcPrev = $prev['spend'] / $prev['link_clicks'];
        $pct = ($cpcNow / $cpcPrev - 1) * 100;
        if ($pct >= (float) $r['t']['pct']) {
            static::add($found, 'high_cpc', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'Cost per click up '.round($pct).'%: '.$c->name,
                'detail' => 'Last 7 days '.number_format($cpcNow, 2).' vs '.number_format($cpcPrev, 2).' '.$c->currency.' the week before.',
                'payload' => ['now' => round($cpcNow, 3), 'prev' => round($cpcPrev, 3), 'pct' => round($pct, 1)]]);
        }
    }

    protected static function zeroImpressions(Agency $a, Carbon $d, array $r, array &$found): void
    {
        $minPrior = (int) ($r['t']['min_prior_impressions'] ?? 500);
        $priorImp = DailyMetric::query()->where('agency_id', $a->id)->whereNotNull('campaign_ref')
            ->whereDate('date', '>=', $d->copy()->subDays(7)->toDateString())
            ->whereDate('date', '<=', $d->copy()->subDays(1)->toDateString())
            ->selectRaw('campaign_ref, SUM(impressions) as imp')->groupBy('campaign_ref')->pluck('imp', 'campaign_ref');
        $yImp = DailyMetric::query()->where('agency_id', $a->id)->whereNotNull('campaign_ref')
            ->whereDate('date', $d->toDateString())
            ->selectRaw('campaign_ref, SUM(impressions) as imp')->groupBy('campaign_ref')->pluck('imp', 'campaign_ref');
        foreach (Campaign::where('agency_id', $a->id)->whereIn('status', ['ACTIVE', 'ENABLED'])->get() as $camp) {
            if (($priorImp[$camp->id] ?? 0) >= $minPrior && ($yImp[$camp->id] ?? 0) <= 0) {
                $acct = AdAccount::find($camp->ad_account_id);
                static::add($found, 'zero_impressions', $r, ['client_id' => $acct?->client_id, 'ad_account_id' => $camp->ad_account_id,
                    'entity_type' => 'campaign', 'entity_id' => $camp->id, 'entity_name' => $camp->name,
                    'title' => 'Active campaign with 0 impressions: '.$camp->name,
                    'detail' => '0 impressions on '.$d->toDateString().' after '.number_format((int) $priorImp[$camp->id]).' impressions in the previous 7 days.']);
            }
        }
    }

    protected static function store(Agency $a, array $found): array
    {
        $stats = ['opened' => 0, 'refreshed' => 0, 'resolved' => 0];
        $live = Alert::where('agency_id', $a->id)->whereIn('status', ['open', 'snoozed'])->get()->keyBy('fingerprint');
        $new = [];
        foreach ($found as $fp => $data) {
            if ($ex = $live[$fp] ?? null) {
                $ex->update(['severity' => $data['severity'], 'title' => $data['title'], 'detail' => $data['detail'] ?? null, 'payload' => $data['payload'] ?? null, 'last_seen_at' => now()]
                    + ($ex->status === 'snoozed' && $ex->snoozed_until?->isPast() ? ['status' => 'open', 'snoozed_until' => null] : []));
                $stats['refreshed']++;
            } else {
                // FS-21: a new Critical alert goes to today's on-call person first.
                $assign = Severity::normalize($data['severity'] ?? null) === 'critical' ? (static::$onCall[$a->id] ??= OnCall::userFor($a)?->id ?? 0) : 0;
                $new[] = Alert::create($data + ['agency_id' => $a->id, 'status' => 'open', 'last_seen_at' => now()] + ($assign ? ['assigned_to' => $assign] : []));
                $stats['opened']++;
            }
        }
        foreach ($live as $fp => $ex) {
            if (! isset($found[$fp])) {
                $ex->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => null]);
                $stats['resolved']++;
            }
        }
        // #175: Critical push to subscribed phones (Pro+), before the email so a phone hears first.
        CriticalPush::dispatch($a, $new);
        foreach ($new as $al) {
            if ($al->severity === 'critical') {
                Webhooks::fire($a, 'alert.critical', ['alert_id' => $al->id, 'rule' => $al->rule_key, 'severity' => $al->severity,
                    'title' => $al->title, 'entity' => $al->entity_name, 'client' => $al->client?->name, 'opened_at' => $al->created_at?->toIso8601String()], $al->client_id);
            }
        }
        $high = collect($new)->where('severity', 'critical');
        if ($high->isNotEmpty()) {
            foreach ($a->members()->where('email_alerts', true)->get() as $u) {
                $mine = $high->filter(fn (Alert $al) => ! $al->client_id || Client::visibleTo($u, $a)->whereKey($al->client_id)->exists());
                if ($mine->isNotEmpty()) {
                    Mail::to($u->email)->queue(new AlertMail($a, $mine->values()->all()));
                }
            }
        }

        return $stats;
    }
}
