<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ReportSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * XS-01 (R4): "This week's clients": the clients that need a touch, ranked, with one line of reason each: open Critical
 * or Warning alerts, a scheduled report going out in the next 7 days, no contact logged in 14 days, a paused client
 * coming back this week. Only clients this person can see.
 */
class WeekList
{
    /** @return list<array{client: Client, score: int, reasons: list<string>}> */
    public static function for(Agency $a, ?User $user, int $limit = 8): array
    {
        $tz = $a->timezone ?: 'UTC';
        $today = CarbonImmutable::now($tz)->startOfDay();
        $clients = Client::visibleTo($user, $a)->whereIn('status', ['active', 'paused'])->orderBy('name')->get();
        if ($clients->isEmpty()) {
            return [];
        }
        $ids = $clients->pluck('id');
        $alerts = Alert::where('agency_id', $a->id)->where('status', 'open')->whereIn('client_id', $ids)
            ->selectRaw('client_id, severity, count(*) as n')->groupBy('client_id', 'severity')->get()->groupBy('client_id');
        $lastContact = ClientContact::whereIn('client_id', $ids)->selectRaw('client_id, max(happened_on) as d')->groupBy('client_id')->pluck('d', 'client_id');
        $schedules = ReportSchedule::where('active', true)->whereIn('client_id', $ids)->get()->keyBy('client_id');

        $out = [];
        foreach ($clients as $c) {
            $score = 0;
            $reasons = [];
            if ($c->status === 'paused') {
                if ($c->paused_until && $c->paused_until->between($today, $today->addDays(7))) {
                    $score += 1;
                    $reasons[] = __('Comes back from pause on :date', ['date' => $c->paused_until->locale(app()->getLocale())->translatedFormat('j M')]);
                }
                if ($reasons) {
                    $out[] = ['client' => $c, 'score' => $score, 'reasons' => $reasons];
                }

                continue;
            }
            $byLevel = ($alerts[$c->id] ?? collect())->pluck('n', 'severity');
            if ($n = (int) ($byLevel['high'] ?? 0)) {
                $score += 3 * $n;
                $reasons[] = trans_choice(':n open Critical alert|:n open Critical alerts', $n, ['n' => $n]);
            }
            if ($n = (int) ($byLevel['medium'] ?? 0)) {
                $score += $n;
                $reasons[] = trans_choice(':n open Warning|:n open Warnings', $n, ['n' => $n]);
            }
            $s = $schedules[$c->id] ?? null;
            if ($s && $s->monthly_day) {
                $next = $today->day <= $s->monthly_day ? $today->day($s->monthly_day) : $today->addMonthNoOverflow()->day($s->monthly_day);
                if ($next->lte($today->addDays(7))) {
                    $score += 1;
                    $reasons[] = __('Monthly report goes out on :date', ['date' => $next->locale(app()->getLocale())->translatedFormat('j M')]);
                }
            }
            $last = $lastContact[$c->id] ?? null;
            $since = $last ? CarbonImmutable::parse($last) : ($c->created_at ? CarbonImmutable::instance($c->created_at) : null);
            if ($since && $since->lt($today->subDays(14))) {
                $score += 1;
                $reasons[] = $last ? __('No contact logged since :date', ['date' => $since->locale(app()->getLocale())->translatedFormat('j M')]) : __('No contact logged yet');
            }
            if ($reasons) {
                $out[] = ['client' => $c, 'score' => $score, 'reasons' => $reasons];
            }
        }
        usort($out, fn ($x, $y) => [$y['score'], $x['client']->name] <=> [$x['score'], $y['client']->name]);

        return array_slice($out, 0, $limit);
    }
}
