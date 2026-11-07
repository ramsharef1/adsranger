<?php

namespace App\Jobs;

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Models\ReportSend;
use App\Support\Modules;
use App\Support\MonthlyReport;
use App\Support\Webhooks;
use App\Support\WhatsAppReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * #106: send one scheduled report (one schedule, one period) to each queued email and WhatsApp recipient.
 */
class SendScheduledReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $scheduleId, public string $kind, public string $period) {}

    public function handle(): void
    {
        $s = ReportSchedule::with(['agency', 'client.agency', 'link'])->find($this->scheduleId);
        $rows = ReportSend::where('report_schedule_id', $this->scheduleId)->where('kind', $this->kind)
            ->where('period', $this->period)->where('status', 'queued')->get();
        if ($rows->isEmpty()) {
            return;
        }
        if (! $s || ! $s->client || ! $s->active || (! config('reports.email_enabled') && ! config('reports.whatsapp_enabled', true))) {
            ReportSend::whereIn('id', $rows->pluck('id'))->update(['status' => 'skipped', 'error' => 'schedule off']);

            return;
        }

        $lang = $s->lang === 'en' ? 'en' : 'ar';
        $pdf = null;
        $hasEmailRows = $rows->contains(fn (ReportSend $r) => ($r->channel ?: 'email') === 'email');
        if ($hasEmailRows && $this->kind === 'monthly' && $s->attach_pdf && MonthlyReport::validMonth($this->period)) {
            $pdf = MonthlyReport::pdf($s->client, $this->period, $lang);
        }
        $link = $s->include_link ? $s->activeLink()?->url() : null;
        $unsubscribed = $s->unsubscribed();
        $waText = null;
        $sent = 0;

        foreach ($rows as $row) {
            if (in_array($row->email, $unsubscribed, true)) {
                $row->update(['status' => 'skipped', 'error' => 'unsubscribed']);

                continue;
            }
            $channel = $row->channel ?: (str_starts_with((string) $row->email, '+') ? 'whatsapp' : 'email');
            if ($channel === 'whatsapp' && (! config('reports.whatsapp_enabled', false) || ! Modules::enabled('whatsapp_reports'))) {
                $row->update(['status' => 'skipped', 'error' => 'whatsapp off']);

                continue;
            }
            if ($channel === 'email' && ! config('reports.email_enabled')) {
                $row->update(['status' => 'skipped', 'error' => 'schedule off']);

                continue;
            }
            try {
                if ($channel === 'whatsapp') {
                    $waText ??= WhatsAppReport::message($s, $this->kind, $this->period, $link);
                    WhatsAppReport::send($row->email, $waText);
                } else {
                    Mail::to($row->email)->locale($lang)->send(new ScheduledReportMail($s, $this->kind, $this->period, $pdf, $link, $s->unsubscribeUrl($row->email)));
                }
                $row->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
                $sent++;
            } catch (\Throwable $e) {
                $row->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 480)]);
                report($e);
            }
        }

        if ($sent) {
            $s->forceFill(['last_sent_at' => now()])->saveQuietly();
            if ($s->agency) {
                Webhooks::fire($s->agency, 'report.sent', ['client' => $s->client->name, 'kind' => $this->kind,
                    'period' => $this->period, 'recipients' => $sent], $s->client_id);
            }
        }
    }
}
