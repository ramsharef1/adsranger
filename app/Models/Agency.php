<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An agency workspace (a tenant of the /app panel).
 * Soft deletes: only the D-61 trial lifecycle archives an agency (App\Support\TrialLifecycle); an archived
 * agency can't be opened (tenant routes 404) and no job touches it, until an admin restores it or it is purged.
 */
class Agency extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'digest_sent_on' => 'date', 'alerts_run_on' => 'date', 'benchmarks_opt_in' => 'boolean', 'benchmarks_opt_in_at' => 'datetime', 'require_two_factor' => 'boolean', 'module_overrides' => 'array', 'custom_domain_verified_at' => 'datetime',
            'trial_readonly_at' => 'datetime', 'trial_expired_at' => 'datetime', 'trial_ending_notified_at' => 'datetime',
            'trial_ended_notified_at' => 'datetime', 'deletion_notified_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function planModel(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan', 'key');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(AgencyInvitation::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(AdAccount::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function dataImports(): HasMany
    {
        return $this->hasMany(DataImport::class);
    }

    public function upgradeRequests(): HasMany
    {
        return $this->hasMany(PlanUpgradeRequest::class);
    }

    public function earlyAccessRequests(): HasMany
    {
        return $this->hasMany(ModuleEarlyAccessRequest::class);
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(WorkspaceMagicLink::class);
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

    /** Admin override: networks switched ON for this agency even if off globally or not in its plan. */
    public function allowedNetworks(): BelongsToMany
    {
        return $this->belongsToMany(AdNetwork::class, 'agency_network_overrides')->withPivotValue('mode', 'allow')->withTimestamps();
    }

    /** Admin override: networks switched OFF for this agency. Wins over everything else. */
    public function blockedNetworks(): BelongsToMany
    {
        return $this->belongsToMany(AdNetwork::class, 'agency_network_overrides')->withPivotValue('mode', 'deny')->withTimestamps();
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'agency';
        if (in_array($base, config('adsranger.reserved_slugs', []), true)) {
            $base .= '-agency';
        }
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->exists()) { // archived agencies keep their slug
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected static function booted(): void
    {
        static::created(fn (Agency $a) => AuditLog::record('agency.created', $a, ['name' => $a->name, 'plan' => $a->plan]));
        static::updated(function (Agency $a) {
            $changed = array_values(array_diff(array_keys($a->getChanges()), ['updated_at']));
            if ($changed) {
                AuditLog::record('agency.updated', $a, ['changed' => $changed]);
            }
        });
    }
}
