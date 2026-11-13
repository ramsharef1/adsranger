<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * #175 (D-104, D-141, experience spec A): Critical alerts as a phone push, Pro and up.
 * Only Critical (D-88) · one push per alert, one grouped push when more than 3 reach a user within 10 minutes ·
 * quiet hours per user (default 23:00–07:00 workspace time) + the workspace weekend: held, then one summary ·
 * "send anyway when spend is 2× the budget or more" per user (off by default).
 */
class CriticalPush
{
    /** @param  Collection<int, Alert>|array<int, Alert>  $alerts  newly opened alerts */
    public static function dispatch(Agency $a, Collection|array $alerts, ?CarbonInterface $now = null): int
    {
        $now = $now ?? now();
        $critical = collect($alerts)->filter(fn (Alert $al) => Severity::normalize($al->severity) === 'critical' && $al->status === 'open')->values();
        if ($critical->isEmpty() || ! config('push.enabled') || ! PlanGate::allows($a, 'phone_push')) {
            return 0;
        }
        $sent = 0;
        $onCall = OnCall::userFor($a, $now)?->id; // FS-21: the on-call person hears Critical alerts even in quiet hours
        foreach ($a->members()->whereHas('pushSubscriptions')->get() as $u) {
            $mine = static::visible($u, $a, $critical);
            if ($mine->isEmpty()) {
                continue;
            }
            if ($u->id !== $onCall && static::isQuiet($u, $a, $now)) {
                $urgent = $u->push_override_spend ? $mine->filter(fn (Alert $al) => static::spendTwiceBudget($al)) : collect();
                foreach ($mine->diff($urgent) as $al) {
                    DB::table('push_held')->insertOrIgnore(['user_id' => $u->id, 'agency_id' => $a->id, 'alert_id' => $al->id, 'created_at' => $now]);
                }
                $mine = $urgent->values();
                if ($mine->isEmpty()) {
                    continue;
                }
            }
            $sent += static::deliver($u, $a, $mine, $now);
        }

        return $sent;
    }

    /** Every 15 minutes: users whose quiet time is over get one summary of what was held. */
    public static function flushHeld(?CarbonInterface $now = null): int
    {
        $now = $now ?? now();
        $sent = 0;
        foreach (DB::table('push_held')->select('user_id', 'agency_id')->distinct()->get() as $row) {
            $u = User::find($row->user_id);
            $a = Agency::find($row->agency_id);
            if (! $u || ! $a || static::isQuiet($u, $a, $now) || PrayerWindows::now($a, $now)) {
                continue; // WK-12 (D-308): the catch-up summary is not urgent, so it also waits for prayer to end
            }
            $ids = DB::table('push_held')->where('user_id', $u->id)->where('agency_id', $a->id)->pluck('alert_id');
            DB::table('push_held')->where('user_id', $u->id)->where('agency_id', $a->id)->delete();
            $open = Alert::whereIn('id', $ids)->where('status', 'open')->get();
            if ($open->isEmpty() || ! PlanGate::allows($a, 'phone_push')) {
                continue;
            }
            $sent += static::push($u, static::text($a, 'held', $open), static::listUrl($a), 'adsr-held-'.$a->id);
        }

        return $sent;
    }

    /** @param  Collection<int, Alert>  $mine */
    protected static function deliver(User $u, Agency $a, Collection $mine, CarbonInterface $now): int
    {
        $key = "push:recent:{$u->id}";
        $recent = collect(Cache::get($key, []))->filter(fn ($t) => $t > $now->getTimestamp() - 60 * (int) config('push.group_minutes', 10))->values();
        $sent = 0;
        if ($recent->count() + $mine->count() > (int) config('push.group_over', 3)) {
            $sent += static::push($u, static::text($a, 'group', $mine), static::listUrl($a), 'adsr-group-'.$a->id);
        } else {
            foreach ($mine as $al) {
                $sent += static::push($u, static::text($a, 'one', collect([$al])), static::alertUrl($a, $al), 'adsr-alert-'.$al->id);
            }
        }
        Alert::whereIn('id', $mine->pluck('id'))->whereNull('pushed_at')->update(['pushed_at' => $now]);
        Cache::put($key, $recent->merge(array_fill(0, $mine->count(), $now->getTimestamp()))->all(), now()->addMinutes(30));

        return $sent;
    }

    /** @param  array{title: string, body: string}  $text */
    public static function push(User $u, array $text, string $url, string $tag): int
    {
        $n = 0;
        foreach ($u->pushSubscriptions as $sub) {
            try {
                $status = WebPush::send($sub, $text + ['url' => $url, 'tag' => $tag]);
                $n += $status >= 200 && $status < 300 ? 1 : 0;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $n;
    }

    /**
     * Copy (spec A): title ≤ 60, body ≤ 110; brand only as the sender; no bdi in push text.
     *
     * @param  Collection<int, Alert>  $alerts
     * @return array{title: string, body: string}
     */
    public static function text(Agency $a, string $kind, Collection $alerts): array
    {
        $loc = $a->language === 'en' ? 'en' : 'ar';
        $clients = $alerts->pluck('client_id')->filter()->unique();
        $clientName = $clients->count() === 1 ? (Client::find($clients->first())?->name ?? $a->name) : $a->name;
        $n = $alerts->count();
        if ($kind === 'one') {
            $al = $alerts->first();
            $rule = __(config("alerts.rules.{$al->rule_key}.label") ?? $al->rule_key, [], $loc);
            $title = __('Critical · :client', ['client' => $clientName], $loc);
            $body = $rule.($al->entity_name ? ' · '.$al->entity_name : '').' · '.($al->created_at?->locale($loc)->diffForHumans() ?? '');
        } elseif ($kind === 'group' && $clients->count() > 1) {
            $title = __('Critical · :client', ['client' => $a->name], $loc);
            $body = trans_choice('{1} :count critical alert on :clients clients|[2,*] :count critical alerts on :clients clients', $n, ['clients' => $clients->count()], $loc);
        } elseif ($kind === 'group') {
            $title = __('Critical · :client', ['client' => $clientName], $loc);
            $body = trans_choice('{1} :count critical alert for :client|[2,*] :count critical alerts for :client', $n, ['client' => $clientName], $loc);
        } else {
            $title = __('Critical · :client', ['client' => $a->name], $loc);
            $body = trans_choice('{1} :count critical alert during your quiet hours|[2,*] :count critical alerts during your quiet hours', $n, [], $loc);
        }

        return ['title' => Str::limit($title, 60, '…'), 'body' => Str::limit($body, 110, '…')];
    }

    public static function isQuiet(User $u, Agency $a, CarbonInterface $now): bool
    {
        $local = $now->copy()->setTimezone($a->timezone ?: 'UTC');
        if ($u->push_weekend_off && in_array($local->isoWeekday(), static::weekend($a), true)) {
            return true;
        }
        $start = (string) ($u->push_quiet_start ?: '23:00');
        $end = (string) ($u->push_quiet_end ?: '07:00');
        // PS-19 (D-307): in Ramadan the default quiet hours follow the late nights (03:00–12:00); own choices stay.
        if ($start === '23:00' && $end === '07:00' && RamadanHours::active($a, $now)) {
            [$start, $end] = RamadanHours::QUIET;
        }
        if ($start === $end) {
            return false;
        }
        $t = $local->format('H:i');

        return $start < $end ? ($t >= $start && $t < $end) : ($t >= $start || $t < $end);
    }

    /** @return array<int, int> ISO weekdays */
    public static function weekend(Agency $a): array
    {
        $map = config('push.weekend', []);

        return $map[strtoupper((string) $a->country)] ?? $map['default'] ?? [5, 6];
    }

    protected static function spendTwiceBudget(Alert $al): bool
    {
        $p = (array) $al->payload;

        return ! empty($p['spend']) && ! empty($p['budget']) && (float) $p['spend'] >= 2 * (float) $p['budget'];
    }

    /** @param  Collection<int, Alert>  $alerts */
    protected static function visible(User $u, Agency $a, Collection $alerts): Collection
    {
        $ids = Client::visibleTo($u, $a)->pluck('id')->all();

        return $alerts->filter(fn (Alert $al) => ! $al->client_id || in_array((int) $al->client_id, array_map('intval', $ids), true))->values();
    }

    public static function alertUrl(Agency $a, Alert $al): string
    {
        return url("/app/{$a->slug}/alerts/{$al->id}");
    }

    public static function listUrl(Agency $a): string
    {
        return url("/app/{$a->slug}/alerts");
    }
}
