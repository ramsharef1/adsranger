<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** An agency workspace (a tenant of the /app panel). */
class Agency extends Model
{
    protected $guarded = [];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(AgencyInvitation::class);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'agency';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
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
