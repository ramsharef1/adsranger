<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * #106: one client's scheduled email report. Monthly on day N (1–28, the previous full month, PDF attached when
 * asked) and/or weekly on an ISO weekday (the last 7 days). "Today" is the workspace's date. A period is sent once:
 * report_sends holds one row per (schedule, kind, period, recipient).
 */
class ReportSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'whatsapp_recipients' => 'array', 'attach_pdf' => 'boolean', 'include_link' => 'boolean', 'active' => 'boolean',
            'monthly_day' => 'integer', 'weekly_day' => 'integer', 'last_sent_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(ClientLink::class, 'client_link_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(ReportSend::class);
    }

    /** Emails that asked to stop (lower case). */
    public function unsubscribed(): array
    {
        return DB::table('report_unsubscribes')->where('report_schedule_id', $this->id)->pluck('email')->all();
    }

    /** Recipients still receiving this report. */
    public function activeRecipients(): array
    {
        return array_values(array_diff(array_map('strtolower', (array) $this->recipients), $this->unsubscribed()));
    }

    /** Validated E.164 WhatsApp numbers still receiving this report (#106). */
    public function activeWhatsAppRecipients(): array
    {
        $out = [];
        foreach ((array) ($this->whatsapp_recipients ?? []) as $raw) {
            if ($n = \App\Support\WhatsAppReport::normalizePhone((string) $raw)) {
                $out[$n] = $n;
            }
        }

        return array_values(array_diff($out, $this->unsubscribed()));
    }

    public function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now($this->agency?->timezone ?: 'UTC');
    }

    /**
     * Sends due on this local date: [[kind, period], ...]. Monthly reports the previous full month from day N on;
     * weekly sends from its weekday on, once per ISO week. A schedule created after this period's send date waits
     * for the next one (no surprise email on save); a missed run (server down) catches up the next day.
     */
    public function dueRuns(?CarbonInterface $localNow = null): array
    {
        $now = CarbonImmutable::instance($localNow ?? $this->localNow());
        $tz = $now->getTimezone();
        $created = $this->created_at ? CarbonImmutable::instance($this->created_at)->setTimezone($tz) : $now;
        $out = [];

        foreach ($this->candidates($now) as [$kind, $period, $date]) {
            if ($now->startOfDay()->gte($date) && $created->lte($date->endOfDay()) && ! $this->alreadySent($kind, $period)) {
                $out[] = [$kind, $period];
            }
        }

        return $out;
    }

    /** [kind, period, send date] for the current month / week. */
    protected function candidates(CarbonImmutable $now): array
    {
        $c = [];
        if ($this->monthly_day) {
            $c[] = ['monthly', $now->startOfMonth()->subMonthNoOverflow()->format('Y-m'), $now->startOfMonth()->addDays($this->monthly_day - 1)];
        }
        if ($this->weekly_day) {
            $c[] = ['weekly', $now->format('o-\WW'), $now->startOfWeek(CarbonInterface::MONDAY)->addDays($this->weekly_day - 1)];
        }

        return $c;
    }

    public function alreadySent(string $kind, string $period): bool
    {
        return $this->sends()->where('kind', $kind)->where('period', $period)->exists();
    }

    /** Next local date an email goes out, or null when the schedule is off. */
    public function nextSendDate(): ?CarbonImmutable
    {
        if (! $this->active || (! $this->monthly_day && ! $this->weekly_day) || (! $this->activeRecipients() && ! $this->activeWhatsAppRecipients())) {
            return null;
        }
        $now = $this->localNow();
        $today = $now->startOfDay();
        $created = $this->created_at ? CarbonImmutable::instance($this->created_at)->setTimezone($now->getTimezone()) : $now;
        $dates = [];
        foreach ($this->candidates($now) as [$kind, $period, $date]) {
            if ($this->alreadySent($kind, $period) || $created->gt($date->endOfDay())) {
                $dates[] = $kind === 'monthly' ? $date->addMonthNoOverflow() : $date->addWeek();
            } else {
                $dates[] = $date->lt($today) ? $today->addDay() : $date; // overdue: the next daily run picks it up
            }
        }

        return $dates ? min($dates) : null;
    }

    public function unsubscribeUrl(string $email): string
    {
        return URL::signedRoute('reports.unsubscribe', ['schedule' => $this->id, 'email' => strtolower($email)]);
    }

    /** The schedule's client link if it still works (revoked or expired links are never re-issued silently). */
    public function activeLink(): ?ClientLink
    {
        $link = $this->link;

        return $link && $link->isActive() ? $link : null;
    }
}
