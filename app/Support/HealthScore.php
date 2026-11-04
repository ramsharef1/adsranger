<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Client;
use Carbon\Carbon;

/**
 * Client health score 0–100 (#35). Weights from config/planning.php (40 results vs target, 20 pacing, 20 trend, 20 tracking + accounts).
 * Each part returns points and a plain-language note so the score can always be explained.
 */
class HealthScore
{
    public const TRACKING_RULES = ['tracking_break', 'tracking_issue', 'spend_no_results', 'connection_problem', 'account_health'];

    /** @return array{score:?int, band:string, color:string, parts:array<string,array{points:float,max:int,note:string}>} */
    public static function compute(Client $c, ?Carbon $today = null): array
    {
        $tz = $c->agency?->timezone ?: 'UTC';
        $today = ($today ?? now($tz))->copy()->startOfDay();
        $w = config('planning.health.weights');
        $days = (int) config('planning.health.window_days');
        $to = $today->copy()->subDay()->toDateString();
        $t = MetricsQuery::totals($c, $today->copy()->subDays($days)->toDateString(), $to);

        if ($t['spend'] <= 0 && array_sum($t['by_type']) <= 0) {
            return ['score' => null, 'band' => 'No recent data', 'color' => 'gray', 'parts' => []];
        }

        $pace = Pacing::month($c, $today);
        $parts = [
            'results' => static::results($c, $t, $pace, $w['results']),
            'pacing' => static::pacing($pace, $w['pacing']),
            'trend' => static::trend($c, $today, $w['trend']),
            'tracking' => static::tracking($c, $w['tracking']),
        ];
        $score = (int) round(array_sum(array_column($parts, 'points')));
        [$band, $color] = static::band($score);

        return ['score' => $score, 'band' => $band, 'color' => $color, 'parts' => $parts];
    }

    public static function band(?int $score): array
    {
        if ($score === null) {
            return ['No recent data', 'gray'];
        }
        foreach (config('planning.health.bands') as $min => $b) {
            if ($score >= $min) {
                return $b;
            }
        }

        return ['At risk', 'danger'];
    }

    protected static function part(float $share, int $max, string $note): array
    {
        return ['points' => round(max(0, min(1, $share)) * $max, 1), 'max' => $max, 'note' => $note];
    }

    protected static function results(Client $c, array $t, array $pace, int $max): array
    {
        $cur = $c->currency;
        if ($t['spend'] > 0 && array_sum($t['by_type']) <= 0) {
            return static::part(0, $max, 'Spend with no results in the last 14 days.');
        }
        if ($c->target_roas && $t['roas']) {
            return static::part($t['roas'] / $c->target_roas, $max, 'ROAS '.number_format($t['roas'], 2).'× vs target '.number_format($c->target_roas, 2).'×.');
        }
        if ($c->target_cpr && $t['cpr']) {
            return static::part($c->target_cpr / $t['cpr'], $max, 'Cost per result '.ResultResolver::money($t['cpr'], $cur).' vs target '.ResultResolver::money($c->target_cpr, $cur).'.');
        }
        if ($pace['target_results'] && $pace['forecast_results'] > 0) {
            return static::part($pace['forecast_results'] / $pace['target_results'], $max,
                'Forecast '.number_format($pace['forecast_results']).' results this month vs target '.number_format($pace['target_results']).'.');
        }

        return static::part(0.75, $max, 'No target set: add a target CPR, ROAS or monthly results on the client.');
    }

    protected static function pacing(array $p, int $max): array
    {
        $tol = (float) config('planning.pacing.tolerance');

        return match ($p['status']) {
            'no_budget' => static::part(0.75, $max, 'No monthly budget set.'),
            'early' => static::part(1, $max, 'Too early in the month to judge pacing.'),
            default => static::part(1 - max(0, abs($p['pace'] - 100) - $tol) / 50, $max,
                'Spent '.round($p['pace']).'% of the expected budget to date.'),
        };
    }

    protected static function trend(Client $c, Carbon $today, int $max): array
    {
        $now = MetricsQuery::totals($c, $today->copy()->subDays(7)->toDateString(), $today->copy()->subDay()->toDateString());
        $prev = MetricsQuery::totals($c, $today->copy()->subDays(14)->toDateString(), $today->copy()->subDays(8)->toDateString());
        if (! $now['cpr'] || ! $prev['cpr'] || $now['main_type'] !== $prev['main_type'] || $now['main_results'] < 5 || $prev['main_results'] < 5) {
            return static::part(0.75, $max, 'Not enough results in the last 2 weeks to judge the trend.');
        }
        $pct = ($now['cpr'] / $prev['cpr'] - 1) * 100;

        return static::part(1 - max(0, $pct) / 50, $max, 'Cost per result '.($pct >= 0 ? 'up ' : 'down ').number_format(abs($pct), 1).'% vs the week before.');
    }

    protected static function tracking(Client $c, int $max): array
    {
        $open = Alert::where('client_id', $c->id)->where('status', 'open')->whereIn('rule_key', self::TRACKING_RULES)->get(['severity']);
        $accounts = $c->adAccounts()->where('is_active', true)->get(['sync_status', 'health']);
        $penalty = $open->sum(fn ($a) => $a->severity === 'high' ? 0.5 : 0.25)
            + $accounts->where('sync_status', 'error')->count() * 0.25
            + $accounts->sum(fn ($a) => collect((array) $a->health)->where(0, 'high')->count()) * 0.25;
        $bits = array_filter([
            $open->count() ? $open->count().' open tracking/account alert(s)' : null,
            ($e = $accounts->where('sync_status', 'error')->count()) ? "$e account(s) failing to sync" : null,
        ]);

        return static::part(1 - $penalty, $max, $bits ? implode('; ', $bits).'.' : 'Tracking and accounts look fine.');
    }
}
