<?php

namespace App\Models;

use App\Mail\AgencyInvitationMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AgencyInvitation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))->first();
    }

    /** Create (or replace) an invite and email it. Only the hash of the token is stored. */
    public static function send(Agency $agency, string $email, string $role, User $inviter): self
    {
        $email = Str::lower(trim($email));
        static::where('agency_id', $agency->id)->where('email', $email)->whereNull('accepted_at')->delete();

        $token = Str::random(48);
        $invite = static::create([
            'agency_id' => $agency->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new AgencyInvitationMail($invite, url('/invite/'.$token)));
        AuditLog::record('team.invited', $agency, ['email' => $email, 'role' => $role]);

        return $invite;
    }

    public function accept(User $user): void
    {
        $agency = $this->agency;
        if (! $agency->members()->whereKey($user->id)->exists()) {
            $agency->members()->attach($user->id, ['role' => $this->role]);
        }
        // An owner invite (sent by a super admin for a new workspace) makes the invitee the workspace owner.
        if ($this->role === 'owner' && ! $agency->owner_id) {
            $agency->forceFill(['owner_id' => $user->id])->save();
        }
        $this->update(['accepted_at' => now()]);
        AuditLog::record('team.joined', $agency, ['user_id' => $user->id, 'role' => $this->role]);
    }
}
