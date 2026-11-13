<?php

namespace App\Support\AiWorkspace;

use App\Jobs\SyncAdAccount;
use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\AiConnection;
use App\Models\AuditLog;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\Fx;
use App\Support\PlanGate;
use Carbon\Carbon;

/**
 * AI Workspace M1 item 7 (spec v2 §3, §14.9, §17.2; D-286): the Free plan «الباقة المجانية» rules. One ad account, one
 * Free workspace per ad account, an ad-spend fence (1,000 USD a month), sync paused after 30 inactive days, daily
 * connector caps by plan. Nothing here ever deletes data.
 */
class FreePlan
{
    public static function isFree(?Agency $agency): bool
    {
        return $agency instanceof Agency && PlanGate::tier($agency) === 'free';
    }

    /** Tool calls per AI connection per day for this workspace's plan (D-286). */
    public static function dailyCap(Agency $agency): int
    {
        return (int) (config('ai_workspace.calls_per_day_by_tier')[PlanGate::tier($agency)] ?? config('ai_workspace.calls_per_day', 200));
    }

    /** The same ad account is already active in another Free workspace (§17.2 A1, hard rule). */
    public static function adAccountTaken(AdAccount $account): bool
    {
        return AdAccount::query()->where('ad_network_id', $account->ad_network_id)->where('external_id', $account->external_id)
            ->where('is_active', true)->where('agency_id', '!=', $account->agency_id)
            ->whereHas('agency', fn ($q) => $q->where('plan', 'free'))->exists();
    }

    /** A platform-disabled account is never connected on Free (§17.2 E1). */
    public static function platformDisabled(AdAccount $account): bool
    {
        return (bool) preg_match('/DISABLE|CLOSED|SUSPEND|BANNED/i', (string) $account->status);
    }

    /**
     * Ad spend of the last full months in USD, and whether the fence applies: both of the last 2 full months above the
     * cap → AI tools go view-only on the 15th of this month (14 days' notice from the 1st).
     *
     * @return array{cap: int, months: array<string, float>, over: bool, view_only_from: ?string, view_only: bool}
     */
    public static function spendFence(Agency $agency, ?Carbon $today = null): array
    {
        $today = ($today ?? now($agency->timezone ?: 'UTC'))->copy()->startOfDay();
        $cap = (int) config('ai_workspace.free_spend_cap_usd', 1000);
        $months = [];
        foreach ([2, 1] as $back) {
            $m = $today->copy()->startOfMonth()->subMonthsNoOverflow($back);
            $rows = DailyMetric::query()->where('agency_id', $agency->id)->whereBetween('date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])
                ->selectRaw('currency, sum(spend) as spend')->groupBy('currency')->get();
            $months[$m->format('Y-m')] = round($rows->sum(fn ($r) => Fx::convert((float) $r->spend, $r->currency, 'USD', $m->copy()->endOfMonth()->toDateString())), 2);
        }
        $over = count(array_filter($months, fn ($v) => $v > $cap)) === 2;
        $from = $over ? $today->copy()->startOfMonth()->addDays((int) config('ai_workspace.free_notice_days', 14))->toDateString() : null;

        return ['cap' => $cap, 'months' => $months, 'over' => $over, 'view_only_from' => $from, 'view_only' => $over && $today->toDateString() >= $from];
    }

    /** No sign-in by any member and no AI call for 30 days → nightly sync pauses until the next sign-in (§3). */
    public static function inactive(Agency $agency): bool
    {
        $since = now()->subDays((int) config('ai_workspace.free_inactive_days', 30));
        $members = $agency->members()->pluck('users.id');
        $signedIn = AuditLog::query()->where('action', 'auth.login')->whereIn('user_id', $members)->where('created_at', '>=', $since)->exists();
        $aiCalls = AiConnection::query()->where('agency_id', $agency->id)->where('last_call_at', '>=', $since)->exists();

        return ! $signedIn && ! $aiCalls && $agency->created_at?->lt($since);
    }

    /** On sign-in: a Free workspace whose sync was paused catches up from its last sync (§3 "catch-up on next login"). */
    public static function catchUp(User $user): int
    {
        $queued = 0;
        foreach ($user->agencies()->where('plan', 'free')->get() as $agency) {
            if (! static::inactive($agency)) {
                continue;
            }
            foreach (AdAccount::where('agency_id', $agency->id)->where('is_active', true)->get() as $a) {
                $tz = $a->timezone ?: ($agency->timezone ?: 'UTC');
                $from = ($a->last_synced_at ? Carbon::parse($a->last_synced_at)->timezone($tz) : now($tz)->subDays(30))->subDays(3)->max(now($tz)->subDays(90));
                SyncAdAccount::dispatch($a->id, $from->toDateString(), now($tz)->toDateString(), 'daily');
                $queued++;
            }
        }

        return $queued;
    }

    /** At least one AI connection that has worked (Free needs one; without it the workspace is view-only). */
    public static function aiConnected(Agency $agency): bool
    {
        return AiConnection::query()->where('agency_id', $agency->id)->where('status', 'verified')->exists();
    }
}
