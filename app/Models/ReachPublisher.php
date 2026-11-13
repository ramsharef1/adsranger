<?php

namespace App\Models;

use Database\Factories\ReachPublisherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** FR-REACH: a site or app that shows Reach offers. Starts as pending_verification until an admin verifies it. */
class ReachPublisher extends Model
{
    /** @use HasFactory<ReachPublisherFactory> */
    use HasFactory;

    public const STATUSES = ['pending_verification', 'verified', 'rejected', 'paused'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['family_safe' => 'boolean', 'category_filters' => 'array', 'blocked_domains' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $p): void {
            $p->ref ??= static::uniqueCode('ref');
            $p->referral_code ??= static::uniqueCode('referral_code');
        });
        static::created(fn (self $p) => ReachWallet::firstOrCreate(['publisher_id' => $p->id], ['currency' => config('reach.wallet.currency')]));
    }

    private static function uniqueCode(string $column): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (static::where($column, $code)->exists() || ! preg_match('/^[a-z0-9]+$/', $code));

        return $code;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(ReachWidget::class, 'publisher_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(ReachWallet::class, 'publisher_id');
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(ReachLedgerEntry::class, 'publisher_id');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
