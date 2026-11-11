<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AuditLog;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WorkspaceMagicLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * M1.1, M1.2 & M1.3: Waitlist and Partner onboarding flows with explicit plan tier + trial length selection.
 */
class WorkspaceOnboarding
{
    /** @return array<string, string> */
    public static function planOptions(): array
    {
        return [
            'solo' => __('Solo'),
            'starter' => __('Starter'),
            'pro' => __('Pro'),
            'agency' => __('Agency'),
        ];
    }

    /** @return array<string, string> */
    public static function trialOptions(): array
    {
        return [
            '14d' => __('14 days'),
            '30d' => __('30 days'),
            'unlimited_beta' => __('Unlimited Beta'),
        ];
    }

    public static function trialEndsAt(string $trialLength): ?Carbon
    {
        return match ($trialLength) {
            '14d' => now()->addDays(14),
            '30d' => now()->addDays(30),
            default => null,
        };
    }

    /**
     * M1.1: Create/reuse workspace with selected tier & trial, send 7-day owner email invite, and record metadata.
     */
    public static function sendEmailInvite(
        WaitlistEntry|PartnerApplication $entry,
        string $plan,
        string $trialLength,
        User $actor
    ): AgencyInvitation {
        return DB::transaction(function () use ($entry, $plan, $trialLength, $actor): AgencyInvitation {
            $agency = static::resolveOrCreateAgency($entry, $plan, $trialLength, 'invite');
            $invite = AgencyInvitation::send($agency, $entry->email, 'owner', $actor, 7 * 24 * 60);

            $entry->forceFill([
                'invited_at' => now(),
                'invited_plan' => $plan,
                'invited_trial_length' => $trialLength,
                'provisioned_agency_id' => $agency->id,
            ])->save();

            AuditLog::record('onboarding.invite_sent', $agency, [
                'source_type' => class_basename($entry),
                'source_id' => $entry->id,
                'email' => $entry->email,
                'plan' => $plan,
                'trial_length' => $trialLength,
            ]);

            return $invite;
        });
    }

    /**
     * M1.2: Immediately provision User + Agency (as Agency Owner) and return [Agency, User, magicLoginUrl].
     *
     * @return array{agency: Agency, user: User, url: string, expires_at: Carbon}
     */
    public static function provisionAndIssueMagicLink(
        WaitlistEntry|PartnerApplication $entry,
        string $plan,
        string $trialLength,
        User $actor
    ): array {
        return DB::transaction(function () use ($entry, $plan, $trialLength, $actor): array {
            $email = Str::lower(trim($entry->email));
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $user = new User([
                    'name' => $entry->name ?: ($entry->agency_name ?: Str::before($email, '@')),
                    'email' => $email,
                    'password' => Str::random(32),
                ]);
                $user->email_verified_at = now();
                $user->save();
            } elseif (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $agency = static::resolveOrCreateAgency($entry, $plan, $trialLength, 'admin');
            if (! $agency->members()->whereKey($user->id)->exists()) {
                $agency->members()->attach($user->id, ['role' => 'owner']);
            } else {
                $agency->members()->updateExistingPivot($user->id, ['role' => 'owner']);
            }

            if ($agency->owner_id !== $user->id) {
                $agency->forceFill(['owner_id' => $user->id])->save();
            }

            [$link, $url] = WorkspaceMagicLink::issue($agency, $user, $actor);

            $entry->forceFill([
                'invited_at' => $entry->invited_at ?? now(),
                'invited_plan' => $plan,
                'invited_trial_length' => $trialLength,
                'provisioned_agency_id' => $agency->id,
                'magic_link_expires_at' => $link->expires_at,
            ])->save();

            AuditLog::record('onboarding.workspace_provisioned', $agency, [
                'source_type' => class_basename($entry),
                'source_id' => $entry->id,
                'owner_user_id' => $user->id,
                'plan' => $plan,
                'trial_length' => $trialLength,
            ]);

            return [
                'agency' => $agency,
                'user' => $user,
                'url' => $url,
                'expires_at' => $link->expires_at,
            ];
        });
    }

    /**
     * M1.2: Regenerate a fresh 7-day magic setup/login link for an already-provisioned entry.
     *
     * @return array{url: string, expires_at: Carbon}
     */
    public static function regenerateMagicLink(WaitlistEntry|PartnerApplication $entry, User $actor): array
    {
        $agency = $entry->provisionedAgency;
        if (! $agency) {
            throw new \RuntimeException('Workspace has not been provisioned yet.');
        }

        $owner = $agency->owner ?? $agency->members()->wherePivot('role', 'owner')->first();
        if (! $owner) {
            $res = static::provisionAndIssueMagicLink(
                $entry,
                $entry->invited_plan ?: $agency->plan,
                $entry->invited_trial_length ?: '14d',
                $actor
            );

            return ['url' => $res['url'], 'expires_at' => $res['expires_at']];
        }

        [$link, $url] = WorkspaceMagicLink::issue($agency, $owner, $actor);
        $entry->forceFill(['magic_link_expires_at' => $link->expires_at])->save();

        return ['url' => $url, 'expires_at' => $link->expires_at];
    }

    protected static function resolveOrCreateAgency(
        WaitlistEntry|PartnerApplication $entry,
        string $plan,
        string $trialLength,
        string $source
    ): Agency {
        $plan = array_key_exists($plan, static::planOptions()) ? $plan : 'starter';
        $endsAt = static::trialEndsAt($trialLength);
        $existing = $entry->provisioned_agency_id ? Agency::withTrashed()->find($entry->provisioned_agency_id) : null;

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->forceFill([
                'plan' => $plan,
                'status' => 'trial',
                'trial_ends_at' => $endsAt,
            ])->save();

            return $existing->fresh();
        }

        $name = trim((string) ($entry->agency_name ?: $entry->name ?: 'Agency Workspace'));
        $country = in_array($entry->country, array_keys((array) config('adsranger.countries', [])), true) ? $entry->country : 'JO';
        $lang = $entry->lang === 'en' ? 'en' : 'ar';

        return Agency::query()->create([
            'name' => $name,
            'slug' => Agency::uniqueSlug($name),
            'plan' => $plan,
            'status' => 'trial',
            'trial_ends_at' => $endsAt,
            'country' => $country,
            'currency' => 'USD',
            'timezone' => 'Asia/Amman',
            'language' => $lang,
            'signup_source' => $source,
        ]);
    }
}
