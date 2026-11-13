<?php

namespace App\Support;

use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\NetworkRequest;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * #127 Network catalog: every network AdsRanger has or plans, with what it means for this workspace and member:
 * connected · available · restricted (limited to other members) · upgrade (on a higher plan) · soon (built, not open yet)
 * · planned (not built). Members can ask for networks that aren't open yet; the asks feed /admin › Network requests.
 */
class NetworkCatalog
{
    public const STATUS_ORDER = ['connected' => 0, 'available' => 1, 'restricted' => 2, 'upgrade' => 3, 'soon' => 4, 'planned' => 5];

    /**
     * @return array<string, list<array{key: string, name: string, category: string, status: string, connected: int, tier: ?string, requested: bool, requests: int}>>
     *                                                                                                                                                                grouped by category in the configured order
     */
    public static function forMember(User $user, Agency $agency): array
    {
        $forAgency = NetworkAccess::forAgency($agency)->pluck('id')->all();
        $forUser = NetworkAccess::forUser($user, $agency)->pluck('id')->all();
        $connected = $agency->connections()->where('status', '!=', 'disconnected')->selectRaw('ad_network_id, count(*) as n')
            ->groupBy('ad_network_id')->pluck('n', 'ad_network_id')->all();
        $tierOf = static::lowestTierPerNetwork();
        $mine = NetworkRequest::where('agency_id', $agency->id)->where('user_id', $user->id)->pluck('network_key')->all();
        $counts = static::requestCounts();

        $rows = [];
        foreach (AdNetwork::orderBy('stage')->orderBy('name')->get() as $n) {
            $status = match (true) {
                ($connected[$n->id] ?? 0) > 0 && in_array($n->id, $forUser, true) => 'connected',
                in_array($n->id, $forUser, true) => 'available',
                in_array($n->id, $forAgency, true) => 'restricted',
                $n->enabled && isset($tierOf[$n->id]) && static::rank($tierOf[$n->id]) > static::rank($agency->plan) => 'upgrade',
                default => 'soon',
            };
            $rows[] = ['key' => $n->key, 'name' => $n->name, 'category' => $n->category ?: 'Other', 'status' => $status,
                'connected' => (int) ($connected[$n->id] ?? 0), 'tier' => $status === 'upgrade' ? $tierOf[$n->id] : null,
                'requested' => in_array($n->key, $mine, true), 'requests' => (int) ($counts[$n->key] ?? 0)];
        }
        foreach (config('network_catalog.planned', []) as $key => $p) {
            $rows[] = ['key' => $key, 'name' => $p['name'], 'category' => $p['category'], 'status' => 'planned', 'connected' => 0, 'tier' => null,
                'requested' => in_array($key, $mine, true), 'requests' => (int) ($counts[$key] ?? 0)];
        }

        $order = array_flip(config('network_catalog.categories', []));
        usort($rows, fn ($a, $b) => [$order[$a['category']] ?? 99, self::STATUS_ORDER[$a['status']], $a['name']]
            <=> [$order[$b['category']] ?? 99, self::STATUS_ORDER[$b['status']], $b['name']]);
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['category']][] = $r;
        }

        return $grouped;
    }

    /** A network can be asked for when it isn't open to the workspace yet (built but closed, or planned). */
    public static function requestable(string $status): bool
    {
        return in_array($status, ['soon', 'planned'], true);
    }

    /** Valid catalog keys members may ask for (built-but-closed networks + planned ones). */
    public static function knownKeys(): array
    {
        return array_merge(AdNetwork::pluck('key')->all(), array_keys(config('network_catalog.planned', [])));
    }

    public static function nameFor(string $key): ?string
    {
        return AdNetwork::where('key', $key)->value('name') ?? (config('network_catalog.planned.'.$key.'.name'));
    }

    /** Key for a free-text suggestion, so two people asking for "Criteo" count together. */
    public static function suggestionKey(string $name): string
    {
        $slug = Str::limit(Str::slug($name, '_'), 60, '');

        return 'other:'.($slug !== '' ? $slug : substr(sha1(mb_strtolower(trim($name))), 0, 12));
    }

    /** Workspaces asking for each network key (one count per workspace, however many of its members ask). */
    public static function requestCounts(): array
    {
        return NetworkRequest::query()->selectRaw('network_key, count(distinct agency_id) as n')->groupBy('network_key')->pluck('n', 'network_key')
            ->map(fn ($v) => (int) $v)->all();
    }

    protected static function rank(?string $tier): int
    {
        return (int) (config('plan_features.tiers')[$tier] ?? 0);
    }

    /** Lowest plan tier whose plan includes each network (by id). */
    protected static function lowestTierPerNetwork(): array
    {
        $out = [];
        $rows = DB::table('ad_network_plan')->join('plans', 'plans.id', '=', 'ad_network_plan.plan_id')->select('ad_network_plan.ad_network_id', 'plans.key')->get();
        foreach ($rows as $r) {
            if (! isset(config('plan_features.tiers')[$r->key]) || $r->key === 'free') {
                continue;
            }
            $cur = $out[$r->ad_network_id] ?? null;
            if ($cur === null || static::rank($r->key) < static::rank($cur)) {
                $out[$r->ad_network_id] = $r->key;
            }
        }

        return $out;
    }
}
