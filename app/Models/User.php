<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
/**
 * Email verification gates /app (#158); accounts that existed before were backfilled as verified.
 * #74: two-step verification with an authenticator app + recovery codes (Filament app authentication; secret and
 * codes are stored encrypted and hidden), and `google_id` once the user has signed in with Google.
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'signup_payload' => 'array',
            'push_weekend_off' => 'boolean',
            'push_override_spend' => 'boolean',
        ];
    }

    /** #175 this user's phones / browsers subscribed to Critical push. */
    public function pushSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** Super admins are created by hand (artisan) and are never held at the verification prompt. */
    public function hasVerifiedEmail(): bool
    {
        return $this->role === 'super_admin' || ! is_null($this->email_verified_at);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' ? $this->role === 'super_admin' : true;
    }

    public function hasTwoStep(): bool
    {
        return filled($this->app_authentication_secret);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /** Agency workspaces this user belongs to (role on the pivot). */
    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class)->withPivot('role')->withTimestamps();
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->agencies;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->agencies()->whereKey($tenant->getKey())->exists();
    }

    public function roleIn(Agency $agency): ?string
    {
        return $this->agencies()->whereKey($agency->getKey())->first()?->pivot?->role;
    }
}
