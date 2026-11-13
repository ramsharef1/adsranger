<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AiBrainItem;
use App\Models\Alert;
use App\Models\DataImport;
use App\Models\ReportSend;
use Carbon\Carbon;

/**
 * PS-29 / NB-20 / NB-21 / PS-31 (R4, D-306): the owner's value card: what AdsRanger did for the workspace this month,
 * counted from the app, and an hours-saved figure labelled «تقدير» with the minutes per task shown (config value_card.minutes).
 */
class ValueCard
{
    /** @return array{month: string, items: list<array{key: string, count: int, minutes: int}>, hours: float} */
    public static function month(Agency $a, ?string $month = null): array
    {
        $tz = $a->timezone ?: 'UTC';
        $start = $month ? Carbon::parse($month.'-01', $tz)->startOfMonth() : now($tz)->startOfMonth();
        $range = [$start->copy()->setTimezone('UTC'), $start->copy()->endOfMonth()->setTimezone('UTC')];
        $min = (array) config('value_card.minutes');

        $counts = [
            'alerts_caught' => Alert::where('agency_id', $a->id)->whereBetween('created_at', $range)->count(),
            'alerts_handled' => Alert::where('agency_id', $a->id)->where('status', 'resolved')->whereNotNull('resolved_by')->whereBetween('resolved_at', $range)->count(),
            'reports_sent' => ReportSend::where('agency_id', $a->id)->where('status', 'sent')->whereBetween('sent_at', $range)->count(),
            'imports' => DataImport::where('agency_id', $a->id)->whereBetween('created_at', $range)->count(),
            'ai_drafts' => AiBrainItem::where('agency_id', $a->id)->where('type', 'output')->whereNotNull('playbook')->whereBetween('created_at', $range)->count(),
        ];
        $items = [];
        $total = 0;
        foreach ($counts as $k => $n) {
            $m = (int) ($min[$k] ?? 0);
            $items[] = ['key' => $k, 'count' => $n, 'minutes' => $m];
            $total += $n * $m;
        }

        return ['month' => $start->format('Y-m'), 'items' => $items, 'hours' => round($total / 60, 1)];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return ['alerts_caught' => __('Alerts caught'), 'alerts_handled' => __('Alerts handled by your team'), 'reports_sent' => __('Scheduled reports sent'),
            'imports' => __('Data imports'), 'ai_drafts' => __('AI drafts checked')];
    }
}
