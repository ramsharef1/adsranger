<?php

namespace App\Support\Business;

use Illuminate\Support\Facades\DB;
use Throwable;

/** PS-27 / NB-03 (log part): count each limit hit and each locked screen per workspace and feature (no user data). */
class UpgradeMoments
{
    /** @var array<string, true> one count per request */
    private static array $seen = [];

    public static function record(int $agencyId, string $feature, string $kind): void
    {
        $k = "$agencyId|$feature|$kind";
        if (isset(self::$seen[$k])) {
            return;
        }
        self::$seen[$k] = true;
        try {
            $where = ['agency_id' => $agencyId, 'feature' => mb_substr($feature, 0, 60), 'kind' => $kind];
            DB::table('upgrade_moments')->insertOrIgnore($where + ['hits' => 0]);
            DB::table('upgrade_moments')->where($where)->update(['hits' => DB::raw('hits + 1'), 'last_at' => now()]);
        } catch (Throwable) {
        }
    }

    public static function reset(): void
    {
        self::$seen = [];
    }

    /** @return list<object> feature totals, most hit first */
    public static function top(int $limit = 20): array
    {
        return DB::table('upgrade_moments')->selectRaw('feature, kind, sum(hits) as hits, count(distinct agency_id) as workspaces, max(last_at) as last_at')
            ->groupBy('feature', 'kind')->orderByDesc('hits')->limit($limit)->get()->all();
    }
}
