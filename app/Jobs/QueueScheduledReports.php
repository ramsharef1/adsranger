<?php

namespace App\Jobs;

use App\Models\ReportSchedule;
use App\Models\ReportSend;
use App\Support\Modules;
use App\Support\Ops\Lanes;
use App\Support\PlanGate;
use App\Support\Trial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * #106, daily: find every scheduled report due today (workspace date), log one queued row per recipient (email +
 * WhatsApp) and queue one SendScheduledReport per schedule + period.
 */
class QueueScheduledReports implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(Lanes::for(self::class));
    }

    public function handle(): void
    {
        if (! config('reports.email_enabled') && ! (config('reports.whatsapp_enabled', false) && Modules::enabled('whatsapp_reports'))) {
            Log::info('scheduled reports: both email_enabled and whatsapp_enabled are off, nothing queued');

            return;
        }

        ReportSchedule::query()->where('active', true)->with(['agency', 'client'])->orderBy('id')
            ->each(function (ReportSchedule $s) {
                $agency = $s->agency;
                if (! $agency || ! in_array($agency->status, ['active', 'trial'], true) || Trial::isReadOnly($agency) || ! $s->client || $s->client->status === 'archived'
                    || PlanGate::locked($agency, 'scheduled_reports')) {
                    return;
                }
                $emails = config('reports.email_enabled') ? $s->activeRecipients() : [];
                $phones = (config('reports.whatsapp_enabled', false) && Modules::enabled('whatsapp_reports')) ? $s->activeWhatsAppRecipients() : [];
                if (! $emails && ! $phones) {
                    return;
                }
                foreach ($s->dueRuns() as [$kind, $period]) {
                    $created = 0;
                    foreach ($emails as $email) {
                        try {
                            ReportSend::create(['agency_id' => $s->agency_id, 'client_id' => $s->client_id, 'report_schedule_id' => $s->id,
                                'kind' => $kind, 'period' => $period, 'channel' => 'email', 'email' => $email, 'status' => 'queued']);
                            $created++;
                        } catch (UniqueConstraintViolationException) {
                        }
                    }
                    foreach ($phones as $phone) {
                        try {
                            ReportSend::create(['agency_id' => $s->agency_id, 'client_id' => $s->client_id, 'report_schedule_id' => $s->id,
                                'kind' => $kind, 'period' => $period, 'channel' => 'whatsapp', 'email' => $phone, 'status' => 'queued']);
                            $created++;
                        } catch (UniqueConstraintViolationException) {
                        }
                    }
                    if ($created) {
                        SendScheduledReport::dispatch($s->id, $kind, $period);
                    }
                }
            });
    }
}
