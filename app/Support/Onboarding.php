<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\ClientLink;
use App\Models\User;
use App\Support\Import\ImportReview;

/**
 * D-188 required onboarding (Business 913 · 19:56). Steps 1–3 (who you are, the workspace, the first client or
 * brand) must be done before the workspace opens; steps 4–7 (result target, connect, team, morning summary) can be
 * skipped and come back as the "Finish setup" checklist on the Daily desk.
 */
class Onboarding
{
    public const REQUIRED_STEPS = 3;

    public const LAST_STEP = 7;

    /** D-189: the third choice reads «شركة تدير إعلاناتها بنفسها» / "A business running its own ads". */
    public const AUDIENCES = ['agency', 'freelancer', 'business'];

    public const INVITE_ROLES = ['admin', 'manager', 'analyst'];

    /** Whether this user must finish onboarding before using this workspace (the owner of a gated workspace). */
    public static function blocks(Agency $agency, ?User $user): bool
    {
        if (! $agency->onboarding_required || $agency->onboarding_completed_at !== null || ! $user || $user->isSuperAdmin()) {
            return false;
        }

        return $agency->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists();
    }

    /** Whether step 6 can invite: a one-seat plan (Solo) sees an upgrade note there instead (D-198). */
    public static function hasTeamStep(Agency $agency): bool
    {
        return PlanLimits::seatsLimit($agency) > 1;
    }

    /** UX-165: a time zone as a city name in the current language (e.g. «عمّان»), not the raw "Asia/Amman". */
    public static function timezoneLabel(?string $tz): string
    {
        $tz = $tz ?: 'Asia/Amman';

        return __('onboarding.timezones.'.$tz) !== 'onboarding.timezones.'.$tz ? __('onboarding.timezones.'.$tz) : $tz;
    }

    /** "Brands" instead of "Clients" for a business running its own ads (FR-032). */
    public static function isInHouse(Agency $agency): bool
    {
        return $agency->audience === 'business';
    }

    /** One audit row per step done or skipped: the internal-only funnel count (no third-party tracking). */
    public static function track(Agency $agency, int $step, string $outcome): void
    {
        AuditLog::record('onboarding.step', $agency, ['step' => $step, 'outcome' => $outcome]);
    }

    /**
     * The "Finish setup" checklist: steps 4–7 with their state, or null when it shouldn't show.
     *
     * @return array{done: int, total: int, items: list<array{step: int, label: string, done: bool, url: string}>}|null
     */
    public static function checklist(Agency $agency, User $user): ?array
    {
        if (! $agency->onboarding_required || $agency->onboarding_completed_at === null || $agency->onboarding_checklist_hidden) {
            return null;
        }
        $base = '/app/'.$agency->slug;
        $client = $agency->clients()->orderBy('id')->first();
        $items = [
            ['step' => 4, 'label' => __('onboarding.step4.title'), 'done' => $client && ($client->monthly_budget || $client->target_cpr), 'url' => $base.'/onboarding?step=4'],
            ['step' => 5, 'label' => __('onboarding.step5.title'), 'done' => $agency->connections()->exists() || $agency->dataImports()->exists(), 'url' => $base.'/onboarding?step=5'],
        ];
        // PS-04 (D-290): the first-report path on uploaded data — sort the imported campaigns, then open the client's report link.
        if ($agency->dataImports()->exists()) {
            $items[] = ['step' => 8, 'label' => __('Sort your imported campaigns'), 'done' => ImportReview::pending($agency) === [], 'url' => $base.'/import-review'];
            $items[] = ['step' => 9, 'label' => __('Preview your first client report'),
                'done' => ClientLink::where('agency_id', $agency->id)->exists(), 'url' => $client ? $base.'/clients/'.$client->id : $base.'/clients'];
        }
        if (static::hasTeamStep($agency)) {
            $items[] = ['step' => 6, 'label' => __('onboarding.step6.title'), 'done' => $agency->members()->count() > 1 || $agency->invitations()->exists(), 'url' => $base.'/onboarding?step=6'];
        }
        $items[] = ['step' => 7, 'label' => __('onboarding.step7.title'), 'done' => (bool) $user->email_digest, 'url' => $base.'/onboarding?step=7'];

        $total = static::REQUIRED_STEPS + count($items);
        $done = static::REQUIRED_STEPS + collect($items)->where('done', true)->count();

        return $done >= $total ? null : ['done' => $done, 'total' => $total, 'items' => $items];
    }
}
