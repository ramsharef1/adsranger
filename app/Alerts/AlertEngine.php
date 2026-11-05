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
use App\Support\Fx;
use App\Support\GoogleInsights;
use App\Support\MetricsQuery;
use App\Support\Pacing;
use App\Support\PlanGate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

/** Evaluates the alert rules for one agency and keeps the inbox in sync (open, refresh, auto-resolve). */
class AlertEngine
{
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
        $min = (float) $r['t']['min_results'];
        if (! $now['cpr'] || ! $prev['cpr'] || $now['main_results'] < $min || $prev['main_results'] < $min || $now['main_type'] !== $prev['main_type']) {
            return;
        }
        $pct = ($now['cpr'] / $prev['cpr'] - 1) * 100;
        if ($pct >= (float) $r['t']['pct']) {
            static::add($found, 'cpr_rising', $r, ['client_id' => $c->id, 'entity_type' => 'client', 'entity_id' => $c->id, 'entity_name' => $c->name,
                'title' => 'Cost per result up '.round($pct).'%: '.$c->name,
                'detail' => 'Last 7 days '.number_format($now['cpr'], 2).' vs '.number_format($prev['cpr'], 2).' '.$c->currency.' the week before.',
                'payload' => ['now' => round($now['cpr'], 4), 'prev' => round($prev['cpr'], 4), 'pct' => round($pct, 1)]]);
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
        foreach (LandingPage::where('agency_id', $a->id)->where('state', 'broken')->where('clicks_30d', '>=', (int) $r['t']['min_clicks'])
            ->where('last_seen_at', '>=', now()->subDays(30))->limit(50)->get() as $lp) {
            static::add($found, 'landing_page', $r, ['client_id' => $lp->client_id, 'entity_type' => 'landing_page', 'entity_id' => $lp->id,
                'entity_name' => mb_substr($lp->url, 0, 190), 'title' => 'Broken landing page',
                'detail' => $lp->url.' — '.($lp->error ?: 'HTTP '.$lp->status_code).'. Ads sent '.$lp->clicks_30d.' clicks here in 30 days.',
                'payload' => ['status' => $lp->status_code]]);
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
                static::add($found, 'account_health', ['severity' => \App\Support\Severity::normalize($sev) === 'critical' ? 'critical' : 'warning'] + $r, ['client_id' => $acct->client_id, 'ad_account_id' => $acct->id,
                    'entity_type' => 'ad_account', 'entity_id' => $acct->id * 100 + $i, 'entity_name' => $acct->name,
                    'title' => 'Account health: '.($acct->network?->name).' · '.$acct->name, 'detail' => $msg]);
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
                $ex->update(['title' => $data['title'], 'detail' => $data['detail'] ?? null, 'payload' => $data['payload'] ?? null, 'last_seen_at' => now()]
                    + ($ex->status === 'snoozed' && $ex->snoozed_until?->isPast() ? ['status' => 'open', 'snoozed_until' => null] : []));
                $stats['refreshed']++;
            } else {
                $new[] = Alert::create($data + ['agency_id' => $a->id, 'status' => 'open', 'last_seen_at' => now()]);
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
        \App\Support\CriticalPush::dispatch($a, $new);
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
