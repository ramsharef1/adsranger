<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An agency's client (a brand or business). Targets live here. */
class Client extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['hide_spend' => 'boolean', 'monthly_budget' => 'float', 'target_cpr' => 'float', 'target_roas' => 'float', 'target_results' => 'integer',
            'health_score' => 'integer', 'health_parts' => 'array', 'health_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(AdAccount::class);
    }

    /** Members limited to this client. Empty = everyone in the workspace. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function budgetPlans(): HasMany
    {
        return $this->hasMany(BudgetPlan::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /** Clients this user may see in this agency. */
    public function scopeVisibleTo(Builder $q, ?User $user, Agency $agency): Builder
    {
        $q->where('agency_id', $agency->id);
        if (! $user || in_array($user->roleIn($agency), ['owner', 'admin'], true) || $user->isSuperAdmin()) {
            return $q;
        }

        return $q->where(fn ($w) => $w->whereDoesntHave('users')->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id)));
    }

    protected static function booted(): void
    {
        static::created(fn (Client $c) => AuditLog::record('client.created', $c->agency, ['client_id' => $c->id, 'name' => $c->name]));
        static::updated(function (Client $c) {
            $changed = array_values(array_diff(array_keys($c->getChanges()), ['updated_at']));
            if ($changed) {
                AuditLog::record('client.updated', $c->agency, ['client_id' => $c->id, 'changed' => $changed]);
            }
        });
        static::deleted(fn (Client $c) => AuditLog::record('client.deleted', $c->agency, ['client_id' => $c->id, 'name' => $c->name]));
    }

    public function links(): HasMany
    {
        return $this->hasMany(ClientLink::class);
    }
}
