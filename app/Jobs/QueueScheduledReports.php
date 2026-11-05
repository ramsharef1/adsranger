<?php

namespace App\Jobs;

use App\Models\ReportSchedule;
use App\Models\ReportSend;
use App\Support\PlanGate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * #106, daily: find every scheduled report due today (workspace date), log one queued row per recipient and
 * queue one SendScheduledReport per schedule + period. The unique send row makes a second run a no-op.
 * Off when config('reports.email_enabled') is false; skips suspended workspaces, archived clients and plans
 * without `scheduled_reports`.
 */
class QueueScheduledReports implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        if (! config('reports.email_enabled')) {
            Log::info('scheduled reports: reports.email_enabled is off, nothing queued');

            return;
        }

        ReportSchedule::query()->where('active', true)->with(['agency', 'client'])->orderBy('id')
            ->each(function (ReportSchedule $s) {
                $agency = $s->agency;
                if (! $agency || ! in_array($agency->status, ['active', 'trial'], true) || ! $s->client || $s->client->status === 'archived'
                    || PlanGate::locked($agency, 'scheduled_reports')) {
                    return;
                }
                $recipients = $s->activeRecipients();
                if (! $recipients) {
                    return;
                }
                foreach ($s->dueRuns() as [$kind, $period]) {
                    $created = 0;
                    foreach ($recipients as $email) {
                        try {
                            ReportSend::create(['agency_id' => $s->agency_id, 'client_id' => $s->client_id, 'report_schedule_id' => $s->id,
                                'kind' => $kind, 'period' => $period, 'email' => $email, 'status' => 'queued']);
                            $created++;
                        } catch (UniqueConstraintViolationException) {
                            // already queued by an overlapping run
                        }
                    }
                    if ($created) {
                        SendScheduledReport::dispatch($s->id, $kind, $period);
                    }
                }
            });
    }
}
