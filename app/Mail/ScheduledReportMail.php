<?php

namespace App\Mail;

use App\Models\ReportSchedule;
use App\Support\MetricsQuery;
use App\Support\MonthlyReport;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * #106: a client's scheduled report email (AR / EN, RTL-safe). Monthly: last full month vs the month before, PDF
 * attached when the schedule asks. Weekly: the last 7 days (to yesterday) vs the 7 before. Spend and cost are left
 * out when the client has hide_spend on. Always carries a signed, per-recipient "Stop these emails" link.
 */
class ScheduledReportMail extends Mailable
{
    use Queueable;

    public function __construct(
        public ReportSchedule $schedule,
        public string $kind,
        public string $period,
        public ?string $pdf = null,
        public ?string $linkUrl = null,
        public string $unsubscribeUrl = '',
    ) {}

    protected function lang(): string
    {
        return $this->schedule->lang === 'en' ? 'en' : 'ar';
    }

    /** [from, to, prevFrom, prevTo, label, prevLabel] */
    public function range(): array
    {
        $lang = $this->lang();
        if ($this->kind === 'monthly') {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $this->period.'-01')->startOfDay();
            $prev = $start->subMonthNoOverflow();

            return [$start->toDateString(), $start->endOfMonth()->toDateString(), $prev->toDateString(), $prev->endOfMonth()->toDateString(),
                $start->locale($lang)->translatedFormat('F Y'), $prev->locale($lang)->translatedFormat('F Y')];
        }
        $to = $this->schedule->localNow()->subDay()->startOfDay();
        $from = $to->subDays(6);

        return [$from->toDateString(), $to->toDateString(), $from->subDays(7)->toDateString(), $from->subDay()->toDateString(),
            $from->format('Y-m-d').' – '.$to->format('Y-m-d'), null];
    }

    public function envelope(): Envelope
    {
        $c = $this->schedule->client;
        [, , , , $label] = $this->range();
        // Unicode isolates keep a Latin client name from flipping an Arabic subject line (EX-13).
        $name = "\u{2068}{$c->name}\u{2069}";
        $subject = $this->kind === 'monthly'
            ? __('report.mail_subject_monthly', ['client' => $name, 'month' => $label], $this->lang())
            : __('report.mail_subject_weekly', ['client' => $name, 'range' => "\u{2066}{$label}\u{2069}"], $this->lang());

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->schedule->agency->name.' · AdsRanger'),
            subject: $subject,
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>']);
    }

    public function content(): Content
    {
        $c = $this->schedule->client;
        [$from, $to, $pf, $pt, $label, $prevLabel] = $this->range();

        return new Content(view: 'mail.scheduled-report', with: [
            'ar' => $this->lang() === 'ar', 'client' => $c, 'agency' => $this->schedule->agency,
            'kind' => $this->kind, 'label' => $label, 'prevLabel' => $prevLabel,
            't' => MetricsQuery::totals($c, $from, $to), 'prev' => MetricsQuery::totals($c, $pf, $pt),
            'hideSpend' => (bool) $c->hide_spend, 'hasPdf' => $this->pdf !== null,
            'linkUrl' => $this->linkUrl, 'unsubscribeUrl' => $this->unsubscribeUrl,
        ]);
    }

    public function attachments(): array
    {
        if ($this->pdf === null) {
            return [];
        }
        $pdf = $this->pdf;

        return [Attachment::fromData(fn () => $pdf, MonthlyReport::filename($this->schedule->client, $this->period, $this->lang()))->withMime('application/pdf')];
    }
}
