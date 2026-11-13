<?php

namespace App\Support\Business;

use App\Models\Agency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EG-33 / NB-13: one row per workspace per month: seats, clients, ad accounts (counts at snapshot time) and what happened
 * in the month (reports sent, report-link views, AI saves, night-shift runs). Counts only; stored now, billing later (LLC).
 */
class UsageLedger
{
    /** @return array<string, int> */
    public static function measure(Agency $a, string $month): array
    {
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $count = fn (string $table, callable $q) => Schema::hasTable($table) ? (int) $q(DB::table($table)->where('agency_id', $a->id)) : 0;

        return [
            'seats' => (int) DB::table('agency_user')->where('agency_id', $a->id)->count(),
            'clients' => $count('clients', fn ($q) => $q->where('status', '!=', 'archived')->count()),
            'ad_accounts' => $count('ad_accounts', fn ($q) => $q->where('is_active', true)->count()),
            'reports_sent' => $count('report_sends', fn ($q) => $q->where('status', 'sent')->whereBetween('sent_at', [$from, $to])->count()),
            'link_views' => $count('client_links', fn ($q) => $q->whereBetween('last_viewed_at', [$from, $to])->sum('views')),
            'ai_saves' => $count('ai_brain_items', fn ($q) => $q->whereBetween('created_at', [$from, $to])->count()),
            'night_runs' => Schema::hasTable('ai_night_runs') && Schema::hasColumn('ai_night_runs', 'agency_id')
                ? $count('ai_night_runs', fn ($q) => $q->whereBetween('created_at', [$from, $to])->count()) : 0,
        ];
    }

    /** Saves the month for every workspace (the scheduler runs it on the 1st for the month just ended). */
    public static function snapshot(?string $month = null): int
    {
        $month ??= now()->subMonthNoOverflow()->format('Y-m');
        $n = 0;
        foreach (Agency::query()->get() as $a) {
            DB::table('usage_ledger')->updateOrInsert(['agency_id' => $a->id, 'month' => $month], self::measure($a, $month) + ['updated_at' => now(), 'created_at' => now()]);
            $n++;
        }

        return $n;
    }
}
