<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * PS-08 (D-296): the phone part of the upload reminder. Once a day, each open "No fresh upload" alert that has just
 * reached 7, 14, 21… days pushes once to members who can see the client (quiet hours respected; phone push is Pro+).
 * The Desk shows the alert itself; no email (D-266).
 */
class UploadReminders
{
    public static function run(?CarbonInterface $now = null): int
    {
        $now ??= now();
        if (! config('push.enabled')) {
            return 0;
        }
        $sent = 0;
        foreach (Alert::where('rule_key', 'upload_stale')->where('status', 'open')->with('client')->get()->groupBy('agency_id') as $agencyId => $alerts) {
            $a = Agency::find($agencyId);
            if (! $a || ! PlanGate::allows($a, 'phone_push')) {
                continue;
            }
            foreach ($alerts as $al) {
                $days = (int) ($al->payload['days'] ?? 0);
                $step = intdiv($days, 7);
                if ($step < 1 || ! Cache::add("upload-reminder:{$al->id}:$step", 1, now()->addDays(8))) {
                    continue; // below 7 days, or this 7-day step was already pushed
                }
                foreach ($a->members()->whereHas('pushSubscriptions')->get() as $u) {
                    if (! Client::visibleTo($u, $a)->whereKey($al->client_id)->exists() || CriticalPush::isQuiet($u, $a, $now)) {
                        continue;
                    }
                    $sent += CriticalPush::push($u, [
                        'title' => __('No fresh data: :client', ['client' => $al->client?->name]),
                        'body' => __('Last upload :n days ago. Export the latest report and import it.', ['n' => $days]),
                    ], url("/app/{$a->slug}/imports"), 'adsr-upload-'.$al->id);
                }
            }
        }

        return $sent;
    }
}
