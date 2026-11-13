<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\AiWorkspace\FreePlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A-17 (IDEA-016): a user's signed-in devices (database sessions), signing others out, and a phone push when a
 * sign-in comes from a browser + system this user hasn't used in the last 180 days. No email (D-266).
 */
class Devices
{
    public const KNOWN_DAYS = 180;

    /** "Chrome on macOS" style label from a user agent; never the raw string. */
    public static function label(?string $ua): string
    {
        $ua = (string) $ua;
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser && $os) {
            return __(':browser on :os', ['browser' => $browser, 'os' => $os]);
        }

        return $browser ?? $os ?? __('Unknown device');
    }

    /** Same browser + system = same device for the new-device check (versions change too often to count). */
    public static function fingerprint(?string $ua): string
    {
        return substr(sha1(static::label($ua)), 0, 16);
    }

    /**
     * @return list<array{id: string, label: string, ip: ?string, last_active: Carbon, current: bool}>
     */
    public static function forUser(User $user, ?string $currentId = null): array
    {
        return DB::table('sessions')->where('user_id', $user->getKey())->orderByDesc('last_activity')->get()
            ->map(fn ($s) => [
                'id' => (string) $s->id,
                'label' => static::label($s->user_agent),
                'ip' => $s->ip_address,
                'last_active' => Carbon::createFromTimestamp($s->last_activity),
                'current' => $currentId !== null && hash_equals((string) $s->id, $currentId),
            ])->values()->all();
    }

    public static function signOut(User $user, string $sessionId, string $currentId): bool
    {
        if (hash_equals($sessionId, $currentId)) {
            return false;
        }
        $gone = DB::table('sessions')->where('user_id', $user->getKey())->where('id', $sessionId)->delete() > 0;
        if ($gone) {
            AuditLog::record('auth.device_signed_out');
        }

        return $gone;
    }

    /** Ends every other session and voids "remember me" cookies, so the other devices must sign in again. */
    public static function signOutOthers(User $user, string $currentId): int
    {
        $n = DB::table('sessions')->where('user_id', $user->getKey())->where('id', '!=', $currentId)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        AuditLog::record('auth.signed_out_other_devices', null, ['sessions' => $n]);

        return $n;
    }

    /** Called on every sign-in: log it, and push the user's phones when the device is new to this account. */
    public static function onLogin(User $user, ?string $ua): void
    {
        $fp = static::fingerprint($ua);
        FreePlan::catchUp($user); // before this sign-in counts as activity
        $previous = AuditLog::where('user_id', $user->getKey())->where('action', 'auth.login');
        $hadAny = (clone $previous)->exists();
        $known = (clone $previous)->where('meta->device', $fp)->where('created_at', '>=', now()->subDays(self::KNOWN_DAYS))->exists();

        AuditLog::create([
            'user_id' => $user->getKey(),
            'action' => 'auth.login',
            'meta' => ['device' => $fp, 'label' => static::label($ua)],
            'ip' => request()?->ip(),
        ]);

        if (! $hadAny || $known) {
            return;
        }

        AuditLog::create([
            'user_id' => $user->getKey(),
            'action' => 'auth.new_device',
            'meta' => ['device' => $fp, 'label' => static::label($ua)],
            'ip' => request()?->ip(),
        ]);

        if (! config('push.enabled')) {
            return;
        }
        foreach (PushSubscription::where('user_id', $user->getKey())->get() as $sub) {
            try {
                WebPush::send($sub, [
                    'title' => __('New sign-in to your AdsRanger account'),
                    'body' => __(':device. If this wasn\'t you, open Signed-in devices, sign out the other devices, then change your password.', ['device' => static::label($ua)]),
                    'url' => '/app',
                ]);
            } catch (\Throwable) {
                // A failed push must not block the sign-in; the new-device row is in the log and on the Devices page.
            }
        }
    }
}
