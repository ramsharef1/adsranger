<?php

namespace App\Support;

use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\Client;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * I-X-1 / UX-81 (EX-08): the "current client" chosen in the top bar. Set with ?client=<id> on any /app page
 * (?client=all clears it), kept per workspace in the session so Livewire updates and later pages keep it.
 * Only a client the member can see is accepted. Daily Desk, Pacing, Alerts and the pages with a client
 * picker (Insights, Google Ads, Top ads, …) use it.
 */
class CurrentClient
{
    private static function key(Agency $a): string
    {
        return 'adsr.client.'.$a->id;
    }

    public static function id(?Agency $a = null): ?int
    {
        $a = $a ?? Filament::getTenant();
        if (! $a instanceof Agency) {
            return null;
        }
        $q = request()->query('client');
        if ($q !== null && ! is_array($q)) {
            static::remember($q, $a);
        }
        $id = (int) session(static::key($a));
        if (! $id) {
            return null;
        }
        if (! Client::visibleTo(auth()->user(), $a)->whereKey($id)->exists()) {
            session()->forget(static::key($a));

            return null;
        }

        return $id;
    }

    /** Store (or clear with 'all' / 0 / '') the current client for this workspace. */
    public static function remember($id, ?Agency $a = null): void
    {
        $a = $a ?? Filament::getTenant();
        if (! $a instanceof Agency) {
            return;
        }
        $id = (int) $id;
        $id ? session([static::key($a) => $id]) : session()->forget(static::key($a));
    }

    public static function resolve(?Agency $a = null): ?Client
    {
        return static::get($a);
    }

    public static function get(?Agency $a = null): ?Client
    {
        $id = static::id($a);

        return $id ? Client::find($id) : null;
    }

    /** Narrow a query to the current client, when one is set. */
    public static function apply(Builder $q, string $column = 'client_id', ?Agency $a = null): Builder
    {
        $id = static::id($a);

        return $id ? $q->where($column, $id) : $q;
    }

    /**
     * UX-83 (EX-23): data freshness for the top bar = the oldest last sync among the active, linked ad accounts
     * (of the current client when one is set). Amber when older than 24 hours.
     *
     * @return array{at: ?Carbon, oldest: ?string, stale: bool, none: bool}
     */
    public static function freshness(?Agency $a = null): array
    {
        $a = $a ?? Filament::getTenant();
        $accts = AdAccount::with('network')->where('agency_id', $a->id)->where('is_active', true)->whereNotNull('client_id');
        static::apply($accts, 'client_id', $a);
        $accts = $accts->get();
        if ($accts->isEmpty()) {
            return ['at' => null, 'oldest' => null, 'stale' => false, 'none' => true];
        }
        $never = $accts->first(fn ($x) => ! $x->last_synced_at);
        $oldest = $never ?? $accts->sortBy('last_synced_at')->first();
        $at = $never ? null : $oldest->last_synced_at;

        return [
            'at' => $at,
            'oldest' => trim(($oldest->name ?: $oldest->external_id).' · '.($oldest->network?->name ?? '')),
            'stale' => ! $at || $at->lt(now()->subDay()),
            'none' => false,
        ];
    }
}
