<?php

namespace App\Auth;

use App\Models\AuditLog;
use Filament\Auth\MultiFactor\App\AppAuthentication as BaseAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * #74 two-step verification: Filament's authenticator-app provider (TOTP + 8 recovery codes) with our audit log
 * (`auth.2fa_enabled`, `auth.2fa_disabled`, `auth.recovery_code_used`; never the secret or a code) and a
 * left-to-right recovery-code input (EX-13). The 6-digit code input is already dir=ltr + inputmode=numeric.
 */
class AppAuthentication extends BaseAppAuthentication
{
    public static function configured(): static
    {
        return static::make()->recoverable()->brandName('AdsRanger');
    }

    public function saveSecret(HasAppAuthentication $user, #[SensitiveParameter] ?string $secret): void
    {
        $had = filled($user->getAppAuthenticationSecret());
        parent::saveSecret($user, $secret);
        $has = filled($secret);

        if ($had !== $has && $user instanceof Model) {
            static::audit($has ? 'auth.2fa_enabled' : 'auth.2fa_disabled', $user);
        }
    }

    public function verifyRecoveryCode(#[SensitiveParameter] string $recoveryCode, ?HasAppAuthenticationRecovery $user = null): bool
    {
        $ok = parent::verifyRecoveryCode($recoveryCode, $user);
        $user ??= auth()->user();

        if ($ok && $user instanceof Model) {
            static::audit('auth.recovery_code_used', $user, ['codes_left' => count($user->fresh()?->getAppAuthenticationRecoveryCodes() ?? [])]);
        }

        return $ok;
    }

    public function getChallengeFormComponents(Authenticatable $user): array
    {
        return array_map(
            fn ($c) => ($c instanceof TextInput && $c->getName() === 'recoveryCode') ? $c->extraInputAttributes(['dir' => 'ltr']) : $c,
            parent::getChallengeFormComponents($user),
        );
    }

    public static function audit(string $action, Model $user, array $meta = []): void
    {
        AuditLog::create(['user_id' => $user->getKey(), 'action' => $action, 'subject_type' => $user::class,
            'subject_id' => $user->getKey(), 'meta' => $meta, 'ip' => request()?->ip()]);
    }
}
