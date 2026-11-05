<?php

namespace App\Support;

use App\Auth\AppAuthentication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * #74 "Continue with Google" for /app only (super admins use password + two-step at /admin).
 * - Hidden, and the routes 404, while GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are empty (D-75: Rami creates the client).
 * - Google must say the email is verified (`email_verified`); otherwise nothing is linked or created.
 * - Existing user (by google_id, else by email) → signs in; google_id is linked on first use. An account whose own
 *   email is still unconfirmed is never linked (someone else may have registered that address with a password).
 * - No user + signup open → the same self-signup rules as /app/register (disposable refused, one trial per business
 *   domain, D-61) and a short "agency name + country" step; the agency is created at once (Google verified the email).
 * - No user + signup closed → refused; the visitor sees the invite-only page (D-53).
 * - A user with two-step verification still has to enter a code (or recovery code) before the session starts.
 */
class GoogleSignIn
{
    public const SIGNUP_SESSION = 'adsr.google.signup';

    public const TWO_STEP_SESSION = 'adsr.google.two_step';

    /** Minutes the held Google identity stays valid for the finish / code step. */
    public const HOLD_MINUTES = 10;

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public static function emailVerified(SocialiteUser $g): bool
    {
        $raw = method_exists($g, 'getRaw') ? $g->getRaw() : ($g->user ?? []);
        $v = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return $v === true || $v === 'true' || $v === 1 || $v === '1';
    }

    /**
     * Decide what a Google callback leads to.
     *
     * @return array{0: string, 1: ?User} outcome: login | two_step | signup | unverified | closed | disposable | taken | admin | mismatch | pending
     */
    public static function resolve(SocialiteUser $g): array
    {
        $email = strtolower(trim((string) $g->getEmail()));
        $id = (string) $g->getId();
        if ($email === '' || $id === '' || ! static::emailVerified($g)) {
            return ['unverified', null];
        }

        $user = User::query()->where('google_id', $id)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            if ($user->isSuperAdmin()) {
                return ['admin', $user];
            }
            if (filled($user->google_id) && $user->google_id !== $id) {
                return ['mismatch', $user];
            }
            if (is_null($user->email_verified_at)) {
                return ['pending', $user];
            }
            if (blank($user->google_id)) {
                $user->forceFill(['google_id' => $id])->save();
            }

            return [$user->hasTwoStep() ? 'two_step' : 'login', $user];
        }

        if (! Signup::isOpen()) {
            return ['closed', null];
        }
        if (Signup::isDisposable($email)) {
            return ['disposable', null];
        }
        if (Signup::emailTaken($email) || Signup::domainHasTrial($email)) {
            return ['taken', null];
        }

        return ['signup', null];
    }

    public static function hold(string $key, array $data): void
    {
        session()->put($key, $data + ['expires' => now()->addMinutes(static::HOLD_MINUTES)->timestamp]);
    }

    public static function held(string $key): ?array
    {
        $d = session()->get($key);

        return (is_array($d) && ($d['expires'] ?? 0) >= now()->timestamp) ? $d : null;
    }

    /** Create the verified user and its trial agency from a held Google identity (Signup rules re-checked by the caller). */
    public static function createAccount(array $held, string $agencyName, ?string $country, string $locale): User
    {
        return DB::transaction(function () use ($held, $agencyName, $country, $locale): User {
            $user = new User([
                'name' => $held['name'] ?: Str::before($held['email'], '@'),
                'email' => $held['email'],
                // A random password nobody knows; "Forgot password?" sets a real one later.
                'password' => Str::password(40),
            ]);
            $user->forceFill([
                'google_id' => $held['google_id'],
                'email_verified_at' => now(),
                'signup_payload' => ['agency_name' => $agencyName, 'country' => $country, 'locale' => $locale === 'en' ? 'en' : 'ar'],
            ])->save();

            Signup::completeVerified($user);

            return $user->refresh();
        });
    }

    public static function audit(User $user, array $meta = []): void
    {
        AppAuthentication::audit('auth.google_login', $user, $meta);
    }
}
