<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationStrategy extends Model
{
    public const MAX_BUDGET_DELTA_PCT = 20.0;

    public const MODES = ['dry_run', 'approval_queue'];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $strategy): void {
            $strategy->max_budget_delta_pct = round(min(self::MAX_BUDGET_DELTA_PCT, max(0.0, (float) $strategy->max_budget_delta_pct)), 2);
            if (! in_array($strategy->execution_mode, self::MODES, true)) {
                $strategy->execution_mode = 'dry_run';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'max_budget_delta_pct' => 'float',
            'is_active' => 'boolean',
            'is_template' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'strategy_id');
    }
}
