<?php

namespace App\Jobs;

use App\Alerts\AlertEngine;
use App\Mail\AlertsDigestMail;
use App\Models\Agency;
use App\Support\Trial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Every 15 minutes: per agency, once a day after 07:00 local time run the alert rules for yesterday;
 * at 08:00 local time send the morning digest to members who opted in.
 */
class RunAlerts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // #24: a snoozed alert comes back when its snooze ends, even if the rule hasn't fired again yet.
        \App\Models\Alert::where('status', 'snoozed')->whereNotNull('snoozed_until')->where('snoozed_until', '<=', now())
            ->update(['status' => 'open', 'snoozed_until' => null]);
        // #175: pushes held during quiet hours / the weekend go out as one summary once the user's quiet time ends.
        \App\Support\CriticalPush::flushHeld();
        Agency::query()->whereIn('status', ['trial', 'active'])->each(function (Agency $a) {
            if (Trial::isReadOnly($a)) {
                return; // D-61: no alert runs or digests for a read-only trial workspace (status may lag a day)
            }
            $local = now($a->timezone ?: 'UTC');
            $today = $local->toDateString();
            if ($local->hour >= 7 && optional($a->alerts_run_on)->toDateString() !== $today) {
                AlertEngine::run($a, $local->copy()->subDay()->toDateString());
                $a->forceFill(['alerts_run_on' => $today])->saveQuietly();
            }
            if ($local->hour >= 8 && optional($a->digest_sent_on)->toDateString() !== $today) {
                $a->forceFill(['digest_sent_on' => $today])->saveQuietly();
                foreach ($a->members()->where('email_digest', true)->get() as $u) {
                    Mail::to($u->email)->queue(new AlertsDigestMail($a, $u, $local->copy()->subDay()->toDateString()));
                }
            }
        });
    }
}
