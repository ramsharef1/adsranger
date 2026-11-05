<?php

namespace App\Support;

use App\Models\Agency;

/** EX-50 point 6: which quotas are at or above 80 % of the limit that applies now (plan limit or trial cap). */
class UsageWarning
{
    public const THRESHOLD = 0.8;

    /** @return list<array{key: string, used: int, limit: int, text: string}> */
    public static function rows(Agency $a): array
    {
        if (Trial::isReadOnly($a)) {
            return [];
        }
        $out = [];
        foreach ([
            'ad_accounts' => [PlanLimits::adAccountsUsed($a), PlanLimits::adAccountsLimit($a)],
            'seats' => [PlanLimits::seatsUsed($a), PlanLimits::seatsLimit($a)],
        ] as $key => [$used, $limit]) {
            if ($limit > 0 && $used >= $limit * static::THRESHOLD) {
                $text = $key === 'seats'
                    ? trans_choice('{1} :used of :count team member|[2,*] :used of :count team members', $limit, ['used' => $used])
                    : trans_choice('{1} :used of :count ad account tracked|[2,*] :used of :count ad accounts tracked', $limit, ['used' => $used]);
                $out[] = ['key' => $key, 'used' => $used, 'limit' => $limit, 'text' => $text];
            }
        }

        return $out;
    }

    public static function title(Agency $a): string
    {
        return Trial::isOn($a) ? __('You are close to your trial limit') : __('You are close to your plan limit');
    }
}
