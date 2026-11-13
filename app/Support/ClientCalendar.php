<?php

namespace App\Support;

use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLicence;
use App\Models\ReportSchedule;
use App\Models\User;
use Carbon\CarbonPeriod;

/**
 * XS-12 (R4): one calendar for all clients (or one): seasons, campaigns ending, report dates, follow-ups, licence expiries
 * and paused clients coming back. Built from what the app already holds; nothing new to enter.
 */
class ClientCalendar
{
    public const KINDS = ['season', 'campaign_end', 'report', 'follow_up', 'licence', 'resume'];

    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return ['season' => __('Season'), 'campaign_end' => __('Campaign ends'), 'report' => __('Report goes out'), 'follow_up' => __('Follow-up'),
            'licence' => __('Licence expires'), 'resume' => __('Client resumes')];
    }

    /** @return list<array{date: string, kind: string, title: string, client_id: ?int, client: ?string}> sorted by date */
    public static function events(Agency $a, ?User $user, string $from, string $to, ?int $clientId = null): array
    {
        $clients = Client::visibleTo($user, $a)->whereIn('status', ['active', 'paused'])->when($clientId, fn ($q) => $q->whereKey($clientId))->get()->keyBy('id');
        $ids = $clients->keys();
        $ar = app()->getLocale() === 'ar';
        $out = [];
        $add = function (string $date, string $kind, string $title, ?int $cid) use (&$out, $clients) {
            $out[] = ['date' => $date, 'kind' => $kind, 'title' => $title, 'client_id' => $cid, 'client' => $cid ? $clients[$cid]?->name : null];
        };

        foreach (MenaCalendar::events($from, $to, $a->country ?: null) as $e) {
            if ($e['start'] >= $from) {
                $add($e['start'], 'season', $ar ? $e['ar'] : $e['label'], null);
            }
        }
        if ($ids->isEmpty()) {
            return static::sorted($out);
        }

        $accounts = AdAccount::whereIn('client_id', $ids)->pluck('client_id', 'id');
        Campaign::whereIn('ad_account_id', $accounts->keys())->whereNotNull('end_time')
            ->whereBetween('end_time', [$from.' 00:00:00', $to.' 23:59:59'])->where(fn ($q) => $q->whereNull('status')->orWhereIn('status', ['ACTIVE', 'active', 'ENABLED', 'enabled']))
            ->get(['name', 'end_time', 'ad_account_id'])->each(fn ($c) => $add(substr((string) $c->end_time, 0, 10), 'campaign_end', (string) $c->name, $accounts[$c->ad_account_id] ?? null));

        foreach (ReportSchedule::where('active', true)->whereIn('client_id', $ids)->get() as $s) {
            foreach (CarbonPeriod::create($from, $to) as $d) {
                if ($s->monthly_day && (int) $d->day === (int) $s->monthly_day) {
                    $add($d->toDateString(), 'report', __('Monthly report'), $s->client_id);
                }
                if ($s->weekly_day && (int) $d->isoWeekday() === (int) $s->weekly_day) {
                    $add($d->toDateString(), 'report', __('Weekly summary'), $s->client_id);
                }
            }
        }
        ClientContact::whereIn('client_id', $ids)->whereNull('next_done_at')->whereBetween('next_on', [$from, $to])->get()
            ->each(fn ($c) => $add($c->next_on->toDateString(), 'follow_up', ClientContact::kindLabels()[$c->kind] ?? $c->kind, $c->client_id));
        ClientLicence::whereIn('client_id', $ids)->whereBetween('expires_on', [$from, $to])->get()
            ->each(fn ($l) => $add($l->expires_on->toDateString(), 'licence', (ClientLicence::kindLabels()[$l->kind] ?? $l->kind).' '.$l->number, $l->client_id));
        foreach ($clients->where('status', 'paused') as $c) {
            if ($c->paused_until && $c->paused_until->toDateString() >= $from && $c->paused_until->toDateString() <= $to) {
                $add($c->paused_until->toDateString(), 'resume', __('Back from pause'), $c->id);
            }
        }

        return static::sorted($out);
    }

    protected static function sorted(array $out): array
    {
        usort($out, fn ($x, $y) => [$x['date'], array_search($x['kind'], self::KINDS, true)] <=> [$y['date'], array_search($y['kind'], self::KINDS, true)]);

        return $out;
    }
}
