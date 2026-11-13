<?php

namespace App\Support\Business;

use App\Models\Agency;
use App\Support\PlanLimits;
use App\Support\Trial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EG-16 = NB-18 = NB-01 = WS-14 = PS-10 (D-289): our own customers, one row per workspace, with red flags for support and
 * churn, plus the first-value funnel across all workspaces. Our data about our customers only; nothing about their clients.
 */
class CustomerHealth
{
    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        $out = [];
        foreach (Agency::query()->orderByDesc('created_at')->get() as $a) {
            $lastLogin = DB::table('users')->join('agency_user', 'users.id', '=', 'agency_user.user_id')->where('agency_user.agency_id', $a->id)->max('users.last_login_at');
            $accounts = (int) DB::table('ad_accounts')->where('agency_id', $a->id)->where('is_active', true)->count();
            $lastSync = DB::table('ad_accounts')->where('agency_id', $a->id)->max('last_synced_at');
            $syncErrors = (int) DB::table('ad_accounts')->where('agency_id', $a->id)->where('is_active', true)->where('sync_status', 'error')->count();
            $reports30 = Schema::hasTable('report_sends') ? (int) DB::table('report_sends')->where('agency_id', $a->id)->where('status', 'sent')->where('sent_at', '>=', now()->subDays(30))->count() : 0;
            $flags = [];
            if ($accounts === 0 && $a->created_at?->lt(now()->subDays(2))) {
                $flags[] = __('Signed up, never connected an ad account');
            }
            if ($syncErrors > 0 && $lastSync && Carbon::parse($lastSync)->lt(now()->subDays(3))) {
                $flags[] = __('Sync failing for 3+ days');
            }
            if ($lastLogin && Carbon::parse($lastLogin)->lt(now()->subDays(14))) {
                $flags[] = __('No sign-in for 14+ days');
            }
            $near = self::nearLimits($a);
            if ($near !== []) {
                $flags[] = __('Near plan limits: :list', ['list' => implode(', ', $near)]);
            }
            $out[] = [
                'id' => $a->id, 'name' => $a->name, 'slug' => $a->slug, 'plan' => $a->plan, 'status' => $a->status,
                'created' => $a->created_at, 'trial_days' => Trial::daysLeft($a), 'last_login' => $lastLogin ? Carbon::parse($lastLogin) : null,
                'accounts' => $accounts, 'last_sync' => $lastSync ? Carbon::parse($lastSync) : null, 'reports30' => $reports30, 'flags' => $flags,
            ];
        }
        usort($out, fn ($x, $y) => count($y['flags']) <=> count($x['flags']));

        return $out;
    }

    /** NB-14 fair use: limits at 80 % or more (quiet signal for a talk, never a block). @return list<string> */
    public static function nearLimits(Agency $a): array
    {
        $near = [];
        foreach (['seats' => [PlanLimits::seatsLimit($a), (int) DB::table('agency_user')->where('agency_id', $a->id)->count()],
            'clients' => [PlanLimits::clientsLimit($a), (int) DB::table('clients')->where('agency_id', $a->id)->where('status', '!=', 'archived')->count()],
            'ad accounts' => [PlanLimits::adAccountsLimit($a), (int) DB::table('ad_accounts')->where('agency_id', $a->id)->where('is_active', true)->count()]] as $what => [$limit, $used]) {
            if ($limit > 0 && $used >= 0.8 * $limit) {
                $near[] = "$what $used/$limit";
            }
        }

        return $near;
    }

    /**
     * PS-10 / WS-14: sign-up → first ad account → first client → first report link opened → first teammate invited.
     *
     * @return list<array{step: string, reached: int, median_hours: ?float}>
     */
    public static function funnel(): array
    {
        $agencies = Agency::query()->get(['id', 'created_at']);
        $steps = [
            [__('Signed up'), fn ($id) => null],
            [__('First ad account'), fn ($id) => DB::table('ad_accounts')->where('agency_id', $id)->min('created_at')],
            [__('First client'), fn ($id) => DB::table('clients')->where('agency_id', $id)->min('created_at')],
            [__('First report link opened by a client'), fn ($id) => Schema::hasTable('client_links') ? DB::table('client_links')->where('agency_id', $id)->where('views', '>', 0)->min('last_viewed_at') : null],
            [__('First teammate invited'), fn ($id) => DB::table('agency_invitations')->where('agency_id', $id)->min('created_at')],
        ];
        $out = [];
        foreach ($steps as $i => [$label, $at]) {
            $hours = [];
            $reached = 0;
            foreach ($agencies as $a) {
                if ($i === 0) {
                    $reached++;

                    continue;
                }
                $t = $at($a->id);
                if ($t) {
                    $reached++;
                    $hours[] = max(0, Carbon::parse($a->created_at)->diffInMinutes(Carbon::parse($t)) / 60);
                }
            }
            sort($hours);
            $median = $hours === [] ? null : round($hours[intdiv(count($hours), 2)], 1);
            $out[] = ['step' => $label, 'reached' => $reached, 'median_hours' => $median];
        }

        return $out;
    }
}
