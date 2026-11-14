<?php

namespace App\Models;

use App\Support\ViewAs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** An agency's client (a brand or business). Targets live here. */
class Client extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['hide_spend' => 'boolean', 'monthly_budget' => 'float', 'target_cpr' => 'float', 'target_roas' => 'float', 'target_results' => 'integer',
            'health_score' => 'integer', 'health_parts' => 'array', 'health_at' => 'datetime', 'ai_scope' => 'array', 'brain_checked_at' => 'datetime', 'paused_until' => 'date', 'paused_at' => 'datetime', 'launch_date' => 'date'];
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

    /** Clients this user may see in this agency (respects #71 ViewAs member/role preview). */
    public function scopeVisibleTo(Builder $q, ?User $user, Agency $agency): Builder
    {
        $q->where('agency_id', $agency->id);
        if (! $user) {
            return $q;
        }
        $preview = ViewAs::current($user, $agency);
        $role = $preview ? $preview['role'] : $user->rawRoleIn($agency);
        if (in_array($role, ['owner', 'admin'], true) || (! $preview && $user->isSuperAdmin())) {
            return $q;
        }
        $targetUser = ($preview && ! empty($preview['user'])) ? $preview['user'] : $user;

        return $q->where(fn ($w) => $w->whereDoesntHave('users')->orWhereHas('users', fn ($u) => $u->where('users.id', $targetUser->id)));
    }

    /** XS-02 (R4): calls, WhatsApp chats and meetings logged with this client. */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
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

    /** #106 scheduled email report (one per client). */
    public function reportSchedule(): HasOne
    {
        return $this->hasOne(ReportSchedule::class);
    }

    public function brandKit(): HasOne
    {
        return $this->hasOne(BrandKit::class);
    }

    public function aeoTrackedPrompts(): HasMany
    {
        return $this->hasMany(AeoTrackedPrompt::class);
    }

    public function aeoCitationRuns(): HasMany
    {
        return $this->hasMany(AeoCitationRun::class);
    }

    public function aeoDomainAudits(): HasMany
    {
        return $this->hasMany(AeoDomainAudit::class);
    }

    public function licences(): HasMany
    {
        return $this->hasMany(ClientLicence::class);
    }

    public function deliveryWeeks(): HasMany
    {
        return $this->hasMany(DeliveryWeek::class);
    }
}
