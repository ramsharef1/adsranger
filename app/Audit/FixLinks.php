<?php

namespace App\Audit;

use App\Models\AdAccount;

/** #173 Deep links to the place in the network's own ad manager where a finding is fixed (FR-016 point 2). Read-only: AdsRanger changes nothing. */
class FixLinks
{
    public static function for(AdAccount $account, ?string $entityType, $entity): ?string
    {
        $key = $account->network?->key;
        $ext = (string) $account->external_id;

        if ($key === 'meta') {
            $act = preg_replace('/^act_/', '', $ext);
            if ($act === '' || ! ctype_digit($act)) {
                return null;
            }
            $base = 'https://adsmanager.facebook.com/adsmanager/manage/';
            $id = $entity && isset($entity->external_id) && ctype_digit((string) $entity->external_id) ? (string) $entity->external_id : null;

            return match ($entityType) {
                'ad' => $base.'ads?act='.$act.($id ? '&selected_ad_ids='.$id : ''),
                'ad_set' => $base.'adsets?act='.$act.($id ? '&selected_adset_ids='.$id : ''),
                'campaign' => $base.'campaigns?act='.$act.($id ? '&selected_campaign_ids='.$id : ''),
                'account' => 'https://business.facebook.com/billing_hub/accounts/details?asset_id='.$act,
                default => $base.'campaigns?act='.$act,
            };
        }

        if ($key === 'google_ads') {
            $cid = preg_replace('/\D/', '', $ext);

            return $cid !== '' ? 'https://ads.google.com/aw/overview?__e='.$cid : null;
        }

        return null;
    }
}
