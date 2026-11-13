<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** FR-REACH: one placement of a publisher, a web JS tag (web_js) or an app offerwall / WebView (app_sdk). */
class ReachWidget extends Model
{
    public const TYPES = ['web_js', 'app_sdk'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $w): void {
            $w->public_key ??= 'rw_'.Str::lower(Str::random(16));
        });
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(ReachPublisher::class, 'publisher_id');
    }

    /** App offerwalls are incentivised placements: only allow_incent offers may appear there (hard firewall). */
    public function isIncentivised(): bool
    {
        return $this->type === 'app_sdk';
    }
}
