<?php

namespace App\Support\Ops;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Support\Carbon;

/** EG-10 / WR-01: open API deadlines with days left; one phone push per deadline at 90, 30 and 7 days. */
class NetworkDeadlines
{
    public const SENT_KEY = 'ops.deadlines.sent';

    public const STEPS = [90, 30, 7];

    /** @return list<array{date: string, network: string, what: string, action: string, done: bool, days: int}> */
    public static function all(): array
    {
        return collect((array) config('network_deadlines'))
            ->map(fn ($d) => $d + ['days' => (int) floor(now()->startOfDay()->diffInDays(Carbon::parse($d['date']), false))])
            ->sortBy('date')->values()->all();
    }

    /** @return list<string> texts pushed in this run */
    public static function watch(): array
    {
        $sent = (array) PlatformSetting::get(self::SENT_KEY, []);
        $texts = [];
        foreach (self::all() as $d) {
            if ($d['done'] || $d['days'] < 0) {
                continue;
            }
            // Every step already reached counts as sent, so crossing two steps at once sends one alert.
            $due = array_filter(self::STEPS, fn ($step) => $d['days'] <= $step);
            $keys = array_map(fn ($step) => $d['date'].'|'.$d['network'].'|'.$step, $due);
            if ($keys !== [] && array_diff($keys, $sent) !== []) {
                $sent = array_values(array_unique(array_merge($sent, $keys)));
                $texts[] = __(':network: :what in :n days (:date). :action', ['network' => $d['network'], 'what' => $d['what'], 'n' => $d['days'], 'date' => $d['date'], 'action' => $d['action']]);
            }
        }
        PlatformSetting::put(self::SENT_KEY, $sent);
        if ($texts !== []) {
            AuditLog::record('ops.network_deadline', null, ['texts' => $texts]);
            if (config('push.enabled')) {
                foreach (PushSubscription::whereIn('user_id', User::where('role', 'super_admin')->pluck('id'))->get() as $sub) {
                    try {
                        WebPush::send($sub, ['title' => __('Ad platform API deadline'), 'body' => mb_substr(implode("\n", $texts), 0, 400), 'url' => '/admin/operations']);
                    } catch (\Throwable) {
                    }
                }
            }
        }

        return $texts;
    }
}
