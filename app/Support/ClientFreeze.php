<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;

/**
 * XS-10 (R4, D-303): pause a client for a while (Eid break, unpaid invoice): alerts, scheduled reports, the night shift
 * and upload reminders stop; data keeps syncing and nothing is deleted. One click to resume, or it resumes itself on
 * the date given.
 */
class ClientFreeze
{
    public const REASONS = ['holiday', 'unpaid', 'client_asked', 'other'];

    /** @return array<string, string> */
    public static function reasonLabels(): array
    {
        return ['holiday' => __('Holiday break'), 'unpaid' => __('Unpaid invoice'), 'client_asked' => __('Client asked to pause'), 'other' => __('Other')];
    }

    public static function pause(Client $client, string $reason, ?string $until, ?User $by): void
    {
        $client->forceFill(['status' => 'paused', 'paused_reason' => in_array($reason, self::REASONS, true) ? $reason : 'other',
            'paused_until' => $until ?: null, 'paused_at' => now()])->save();
        AuditLog::record('client.paused', $client->agency, ['client_id' => $client->id, 'reason' => $client->paused_reason, 'until' => $until, 'by' => $by?->id]);
    }

    public static function resume(Client $client, ?User $by): void
    {
        $client->forceFill(['status' => 'active', 'paused_reason' => null, 'paused_until' => null, 'paused_at' => null])->save();
        AuditLog::record('client.resumed', $client->agency, ['client_id' => $client->id, 'by' => $by?->id]);
    }

    /** Daily: clients whose resume date (in the workspace's time zone) has come are switched back on. */
    public static function resumeDue(): int
    {
        $n = 0;
        Client::with('agency')->where('status', 'paused')->whereNotNull('paused_until')->each(function (Client $c) use (&$n) {
            if ($c->paused_until->toDateString() <= now($c->agency?->timezone ?: 'UTC')->toDateString()) {
                static::resume($c, null);
                $n++;
            }
        });

        return $n;
    }
}
