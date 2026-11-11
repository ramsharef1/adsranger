<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Client;
use Carbon\Carbon;

/**
 * M5.1 Client health score 0–100 (#35):
 * - Standard weights: 35% Budget Pacing accuracy + 35% Active Alerts & Rejected Ads + 30% Target CPR/ROAS achievement.
 * - When a client has neither Target CPR nor Target ROAS set, proportionally re-weights to 50% Pacing + 50% Alerts/Rejected Ads
 *   and exposes `no_target_set = true` (`No target set` / `لم يُحدَّد مستهدف` badge).
 */
class HealthScore
{
    /** @return array{score:?int, band:string, color:string, no_target_set:bool, no_target_badge:?string, parts:array<string,array{points:float,max:int,note:string}>} */
    public static function compute(Client $c, ?Carbon $today = null): array
    {
        $tz = $c->agency?->timezone ?: 'UTC';
        $today = ($today ?? now($tz))->copy()->startOfDay();
        $w = (array) config('planning.health.weights', ['pacing' => 35, 'alerts' => 35, 'targets' => 30]);
        $days = (int) config('planning.health.window_days', 14);
        $to = $today->copy()->subDay()->toDateString();
        $t = MetricsQuery::totals($c, $today->copy()->subDays($days)->toDateString(), $to);

        $hasTarget = ((float) ($c->target_cpr ?? 0) > 0) || ((float) ($c->target_roas ?? 0) > 0);
        $noTargetBadge = $hasTarget ? null : __('No target set');

        if ($t['spend'] <= 0 && array_sum($t['by_type']) <= 0) {
            return [
                'score' => null,
                'band' => 'No recent data',
                'color' => 'gray',
                'no_target_set' => ! $hasTarget,
                'no_target_badge' => $noTargetBadge,
                'parts' => [],
            ];
        }

        $pace = Pacing::month($c, $today);

        if ($hasTarget) {
            $pacingMax = (int) ($w['pacing'] ?? 35);
            $alertsMax = (int) ($w['alerts'] ?? 35);
            $targetsMax = (int) ($w['targets'] ?? 30);

            $parts = [
                'pacing' => static::pacing($pace, $pacingMax),
                'alerts' => static::alertsAndRejected($c, $alertsMax),
                'targets' => static::targets($c, $t, $targetsMax),
            ];
        } else {
            $parts = [
                'pacing' => static::pacing($pace, 50),
                'alerts' => static::alertsAndRejected($c, 50),
            ];
        }

        $score = (int) round(array_sum(array_column($parts, 'points')));
        [$band, $color] = static::band($score);

        return [
            'score' => $score,
            'band' => $band,
            'color' => $color,
            'no_target_set' => ! $hasTarget,
            'no_target_badge' => $noTargetBadge,
            'parts' => $parts,
        ];
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

    protected static function targets(Client $c, array $t, int $max): array
    {
        $cur = $c->currency;
        if ($t['spend'] > 0 && array_sum($t['by_type']) <= 0) {
            return static::part(0, $max, 'Spend with no results in the last 14 days.');
        }
        if ((float) ($c->target_roas ?? 0) > 0) {
            if (! $t['roas'] || $t['roas'] <= 0) {
                return static::part(0, $max, 'No tracked ROAS in the last 14 days vs target '.number_format((float) $c->target_roas, 2).'×.');
            }

            return static::part($t['roas'] / (float) $c->target_roas, $max, 'ROAS '.number_format($t['roas'], 2).'× vs target '.number_format((float) $c->target_roas, 2).'×.');
        }
        if ((float) ($c->target_cpr ?? 0) > 0) {
            if (! $t['cpr'] || $t['cpr'] <= 0) {
                return static::part(0, $max, 'No cost per result yet vs target '.ResultResolver::money((float) $c->target_cpr, $cur).'.');
            }

            return static::part((float) $c->target_cpr / $t['cpr'], $max, 'Cost per result '.ResultResolver::money($t['cpr'], $cur).' vs target '.ResultResolver::money((float) $c->target_cpr, $cur).'.');
        }

        return static::part(0, $max, 'No target set.');
    }

    protected static function pacing(array $p, int $max): array
    {
        $tol = (float) config('planning.pacing.tolerance', 10);

        return match ($p['status']) {
            'no_budget' => static::part(0.75, $max, 'No monthly budget set.'),
            'early' => static::part(1, $max, 'Too early in the month to judge pacing.'),
            default => static::part(1 - max(0, abs($p['pace'] - 100) - $tol) / 50, $max,
                'Spent '.round($p['pace']).'% of the expected budget to date.'),
        };
    }

    protected static function alertsAndRejected(Client $c, int $max): array
    {
        $open = Alert::query()->where('client_id', $c->id)->where('status', 'open')->get(['severity']);
        $accounts = $c->adAccounts()->where('is_active', true)->get(['sync_status', 'health']);

        $rejectedCount = 0;
        $criticalHealthCount = 0;
        foreach ($accounts as $acct) {
            foreach ((array) $acct->health as $issue) {
                [$sev, $msg] = array_pad((array) $issue, 2, '');
                if (stripos((string) $msg, 'rejected') !== false) {
                    $num = (int) preg_replace('/\D+/', '', (string) $msg);
                    $rejectedCount += max(1, $num);
                } elseif (Severity::normalize($sev) === 'critical') {
                    $criticalHealthCount++;
                }
            }
        }

        $syncErrors = $accounts->where('sync_status', 'error')->count();
        $penalty = $open->sum(fn ($a) => $a->severity === 'critical' ? 0.35 : ($a->severity === 'warning' ? 0.20 : 0.10))
            + ($rejectedCount * 0.20)
            + ($criticalHealthCount * 0.25)
            + ($syncErrors * 0.25);

        $bits = array_filter([
            $open->count() ? $open->count().' active alert(s)' : null,
            $rejectedCount ? $rejectedCount.' rejected ad(s)' : null,
            $syncErrors ? $syncErrors.' account(s) failing to sync' : null,
        ]);

        return static::part(1 - $penalty, $max, $bits ? implode('; ', $bits).'.' : 'No active alerts or rejected ads.');
    }
}
