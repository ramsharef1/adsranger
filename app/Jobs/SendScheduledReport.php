<?php

namespace App\Jobs;

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Models\ReportSend;
use App\Support\MonthlyReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * #106: send one scheduled report (one schedule, one period) to each queued recipient. The PDF is rendered once.
 * Recipients who unsubscribed since the rows were queued are skipped. Each row ends sent, skipped or failed.
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
        if (! $s || ! $s->client || ! $s->active || ! config('reports.email_enabled')) {
            ReportSend::whereIn('id', $rows->pluck('id'))->update(['status' => 'skipped', 'error' => 'schedule off']);

            return;
        }

        $lang = $s->lang === 'en' ? 'en' : 'ar';
        $pdf = null;
        if ($this->kind === 'monthly' && $s->attach_pdf && MonthlyReport::validMonth($this->period)) {
            $pdf = MonthlyReport::pdf($s->client, $this->period, $lang);
        }
        $link = $s->include_link ? $s->activeLink()?->url() : null;
        $unsubscribed = $s->unsubscribed();
        $sent = 0;

        foreach ($rows as $row) {
            if (in_array($row->email, $unsubscribed, true)) {
                $row->update(['status' => 'skipped', 'error' => 'unsubscribed']);

                continue;
            }
            try {
                Mail::to($row->email)->locale($lang)->send(new ScheduledReportMail($s, $this->kind, $this->period, $pdf, $link, $s->unsubscribeUrl($row->email)));
                $row->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
                $sent++;
            } catch (\Throwable $e) {
                $row->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 480)]);
                report($e);
            }
        }

        if ($sent) {
            $s->forceFill(['last_sent_at' => now()])->saveQuietly();
        }
    }
}
