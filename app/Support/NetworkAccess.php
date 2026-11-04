<?php

namespace App\Support;

use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who may use which ad network. Four layers, checked in this order:
 *  1. Agency override "deny" (admin)   -> hidden, always.
 *  2. Agency override "allow" (admin)  -> shown, even if off globally or not in the plan (beta).
 *  3. Global switch + plan             -> shown only if the network is enabled AND the agency's plan includes it.
 *  4. Per-user limit (agency owner)    -> if the agency limited a network to chosen members, only they (and owners/admins) see it.
 */
class NetworkAccess
{
    public static function forAgency(Agency $agency): Collection
    {
        $planIds = $agency->planModel?->networks()->pluck('ad_networks.id') ?? collect();
        $overrides = DB::table('agency_network_overrides')->where('agency_id', $agency->id)->get()->groupBy('ad_network_id');

        return AdNetwork::orderBy('stage')->orderBy('name')->get()->filter(function (AdNetwork $n) use ($planIds, $overrides) {
            $modes = collect($overrides->get($n->id, []))->pluck('mode');
            if ($modes->contains('deny')) {
                return false;
            }
            if ($modes->contains('allow')) {
                return true;
            }

            return $n->enabled && $planIds->contains($n->id);
        })->values();
    }

    public static function forUser(User $user, Agency $agency): Collection
    {
        $limits = DB::table('agency_network_users')->where('agency_id', $agency->id)->get()->groupBy('ad_network_id');
        $isManager = in_array($user->roleIn($agency), ['owner', 'admin'], true);

        return static::forAgency($agency)->filter(function (AdNetwork $n) use ($limits, $user, $isManager) {
            $chosen = $limits->get($n->id);

            return $isManager || ! $chosen || collect($chosen)->contains('user_id', $user->id);
        })->values();
    }

    public static function allows(User $user, Agency $agency, AdNetwork $network): bool
    {
        return static::forUser($user, $agency)->contains('id', $network->id);
    }

    /** Member ids a network is limited to inside an agency (empty = everyone). */
    public static function limitedTo(Agency $agency, AdNetwork $network): array
    {
        return DB::table('agency_network_users')->where('agency_id', $agency->id)->where('ad_network_id', $network->id)->pluck('user_id')->all();
    }

    public static function setLimitedTo(Agency $agency, AdNetwork $network, array $userIds): void
    {
        DB::table('agency_network_users')->where('agency_id', $agency->id)->where('ad_network_id', $network->id)->delete();
        foreach (array_unique($userIds) as $uid) {
            DB::table('agency_network_users')->insert(['agency_id' => $agency->id, 'ad_network_id' => $network->id, 'user_id' => $uid]);
        }
    }
}
