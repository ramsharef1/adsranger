<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * #158 self-signup behind the `signup.open` flag (D-42; off by default, invite-only beta D-40/D-41).
 * Abuse limits: per-IP throttle (in the Register page), honeypot, disposable domains, one trial per business domain.
 */
class Signup
{
    public const FLAG = 'signup.open';

    public const ALLOWLIST = 'signup.trial_domain_allowlist';

    public static function isOpen(): bool
    {
        return (bool) PlatformSetting::get(static::FLAG, (bool) config('adsranger.signup.open', false));
    }

    public static function domainOf(string $email): string
    {
        return strtolower(trim((string) substr(strrchr(trim($email), '@') ?: '', 1)));
    }

    public static function isDisposable(string $email): bool
    {
        $d = static::domainOf($email);

        return $d !== '' && in_array($d, config('adsranger.signup.disposable_domains', []), true);
    }

    public static function isFreeMail(string $domain): bool
    {
        return in_array($domain, config('adsranger.signup.free_mail_domains', []), true);
    }

    /** Domains an admin lets start more than one trial (Platform settings). */
    public static function allowlist(): array
    {
        return array_values(array_filter(array_map(fn ($d) => strtolower(trim((string) $d)), (array) PlatformSetting::get(static::ALLOWLIST, []))));
    }

    public static function emailTaken(string $email): bool
    {
        return User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->exists();
    }

    /**
     * D-61 one trial per workspace: a business domain that already has an account (any user, invited or
     * signed up, verified or pending) cannot start another trial, unless the domain is on the allowlist.
     * Shared mail providers (gmail.com …) are exempt; the verified-email check covers them.
     */
    public static function domainHasTrial(string $email): bool
    {
        $d = static::domainOf($email);
        if ($d === '' || static::isFreeMail($d) || in_array($d, static::allowlist(), true)) {
            return false;
        }

        return User::query()->whereRaw('LOWER(email) LIKE ?', ['%@'.$d])->exists();
    }

    /**
     * After the email is verified: create the agency (owner, Starter plan on a 14-day Pro trial, D-61),
     * audit `agency.self_signup`, and clear the held signup data. Does nothing for other users.
     */
    public static function completeVerified(User $user): ?Agency
    {
        $p = $user->signup_payload;
        if (! is_array($p) || empty($p['agency_name'])) {
            return null;
        }

        return DB::transaction(function () use ($user, $p) {
            $user->refresh();
            if (! is_array($user->signup_payload) || $user->agencies()->wherePivot('role', 'owner')->exists()) {
                return null;
            }
            $locale = in_array($p['locale'] ?? null, ['ar', 'en'], true) ? $p['locale'] : 'ar';
            $agency = Agency::create([
                'name' => $p['agency_name'],
                'slug' => Agency::uniqueSlug($p['agency_name']),
                'owner_id' => $user->id,
                'country' => $p['country'] ?? null,
                'language' => $locale,
                'plan' => 'starter',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays((int) config('adsranger.trial_days', 14)),
                'signup_source' => 'self_signup',
                'onboarding_required' => true,
            ]);
            $agency->members()->attach($user->id, ['role' => 'owner']);
            $user->forceFill(['signup_payload' => null])->save();
            AuditLog::create(['user_id' => $user->id, 'action' => 'agency.self_signup', 'subject_type' => Agency::class,
                'subject_id' => $agency->id, 'meta' => ['email_domain' => static::domainOf($user->email), 'country' => $agency->country,
                    'trial_ends_at' => $agency->trial_ends_at->toIso8601String()], 'ip' => request()?->ip()]);

            return $agency;
        });
    }
}
