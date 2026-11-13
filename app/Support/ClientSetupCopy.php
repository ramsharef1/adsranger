<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ReportSchedule;

/**
 * PS-13 (D-290): copy one client's setup to others — targets (cost per result, results, ROAS, call result, vertical,
 * market, hide spend) and the report schedule (days, language, PDF / link). Recipients are never copied: they belong to
 * each client, so a copied schedule starts off until the new client's recipients are added.
 */
class ClientSetupCopy
{
    public const TARGET_FIELDS = ['target_cpr', 'target_results', 'target_roas', 'call_result', 'vertical', 'market', 'hide_spend'];

    /**
     * @param  list<int>  $toIds
     * @param  list<string>  $parts  targets · schedule
     * @return int clients changed
     */
    public static function copy(Client $from, array $toIds, array $parts): int
    {
        $source = $from->fresh();
        $targets = Client::where('agency_id', $from->agency_id)->whereIn('id', $toIds)->whereKeyNot($from->id)->get();
        $schedule = in_array('schedule', $parts, true) ? ReportSchedule::where('client_id', $from->id)->first() : null;
        foreach ($targets as $to) {
            if (in_array('targets', $parts, true)) {
                $to->forceFill(array_filter($source->only(self::TARGET_FIELDS), fn ($v) => $v !== null))->save();
            }
            if ($schedule) {
                $existing = ReportSchedule::where('client_id', $to->id)->first();
                $values = $schedule->only(['monthly_day', 'weekly_day', 'lang', 'attach_pdf', 'include_link']);
                $existing
                    ? $existing->forceFill($values)->save()
                    : ReportSchedule::create($values + ['agency_id' => $to->agency_id, 'client_id' => $to->id, 'recipients' => [], 'active' => false, 'created_by' => auth()->id()]);
            }
        }
        if ($targets->isNotEmpty()) {
            AuditLog::record('client.setup_copied', $from->agency, ['from' => $from->id, 'to' => $targets->pluck('id')->all(), 'parts' => $parts]);
        }

        return $targets->count();
    }
}
