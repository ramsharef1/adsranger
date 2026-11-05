<?php

namespace App\Support;

use App\Filament\Pages\PlanUsage;
use App\Models\Agency;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * EX-50 point 6 quota notice at a limit (ADSR-031 part 2 for the trial caps): what was reached, then
 * "See plans" for owner/admin, or "Ask your workspace owner to upgrade" for everyone else (EX-37).
 */
class QuotaNotice
{
    /** @param 'ad_accounts'|'seats' $key */
    public static function make(Agency $a, ?User $user, string $key): Notification
    {
        $limit = $key === 'seats' ? PlanLimits::seatsLimit($a) : PlanLimits::adAccountsLimit($a);
        $canUpgrade = PlanGate::canUpgrade($user, $a);

        if (PlanLimits::isTrialCap($a, $key)) {
            $title = $key === 'seats'
                ? trans_choice('{1} Trial limit reached: :count team member|[2,*] Trial limit reached: :count team members', $limit)
                : trans_choice('{1} Trial limit reached: :count ad account|[2,*] Trial limit reached: :count ad accounts', $limit);
            $body = $key === 'seats'
                ? __('Members and pending invites both count. Revoke an invite or remove a member to free a seat.')
                : __('Stop tracking an account to free a place.');
        } elseif ($key === 'seats') {
            // Unchanged wording for plan limits outside the trial.
            $title = __('No free seats');
            $body = trans_choice('{1} Your plan includes :count seat (members + pending invites).|[2,*] Your plan includes :count seats (members + pending invites).', $limit).' '.__('Revoke an invite, remove a member, or upgrade.');
        } else {
            $title = __('Plan limit reached');
            $body = trans_choice('{1} Your plan tracks up to :count ad account.|[2,*] Your plan tracks up to :count ad accounts.', $limit).' '.__('Stop tracking one, or upgrade.');
        }

        $n = Notification::make('quota-'.$key)->title($title)->warning()->persistent();
        if ($canUpgrade) {
            $n->body($body)->actions([
                Action::make('plans')->label(__('See plans'))->button()->url(PlanUsage::getUrl(tenant: $a)),
            ]);
        } else {
            $n->body($body.' '.__('plan.ask_owner').'.');
        }

        return $n;
    }
}
