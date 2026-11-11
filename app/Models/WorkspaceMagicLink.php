<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkspaceMagicLink extends Model
{
    public const VALID_DAYS = 7;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public static function canIssueFor(User $user): bool
    {
        return ! $user->isSuperAdmin() && ! $user->hasTwoStep()
            && ! static::query()->where('user_id', $user->id)->whereNotNull('used_at')->exists();
    }

    public static function findByToken(string $token): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $token))->first();
    }

    /**
     * Issue a fresh 7-day magic setup/login link and invalidate prior unused links for this agency+user.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Agency $agency, User $user, ?User $actor = null): array
    {
        // E1 review (D-249): a magic link signs in without a password or two-step code, so it is only for a brand-new
        // account that has never been set up. Never for a super admin, a user with two-step on, or one who used a link.
        if (! static::canIssueFor($user)) {
            throw new \DomainException('existing_account');
        }

        static::query()
            ->where('agency_id', $agency->id)
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $plain = Str::random(56);
        $record = static::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'created_by' => $actor?->id,
            'expires_at' => now()->addDays(static::VALID_DAYS),
        ]);

        AuditLog::record('agency.magic_link_issued', $agency, [
            'for_user_id' => $user->id,
            'expires_at' => $record->expires_at->toIso8601String(),
        ]);

        return [$record, url('/magic-login/'.$plain)];
    }
}
