<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A-18: rows made inside a workspace keep its id; outside the panel (AI connector, jobs) the subject's workspace is used. Admin and console rows without a subject have none. */
    public static function record(string $action, ?Model $subject = null, array $meta = []): void
    {
        $tenant = Filament::getTenant();

        static::create([
            'user_id' => auth()->id(),
            'agency_id' => $tenant instanceof Agency ? $tenant->getKey() : ($subject?->getAttribute('agency_id') ?: null),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
            'ip' => request()?->ip(),
        ]);
    }
}
