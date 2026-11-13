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
    /** D-214: a new invite is valid for 60 minutes (Rami, 2026-11-07). */
    public const VALID_MINUTES = 60;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'via_link' => 'boolean'];
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
    public static function send(Agency $agency, string $email, string $role, User $inviter, ?int $validMinutes = null): self
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
            'expires_at' => now()->addMinutes($validMinutes ?? static::VALID_MINUTES),
        ]);

        Mail::to($email)->send(new AgencyInvitationMail($invite, url('/invite/'.$token)));
        AuditLog::record('team.invited', $agency, ['email' => $email, 'role' => $role]);

        return $invite;
    }

    /** EH-11 (D-291 / D-293): a link valid for 24 hours, one use. Holds a seat until used or expired; no email is sent. */
    public const LINK_VALID_MINUTES = 24 * 60;

    /** @return array{0: self, 1: string} the invite and its link (the token is shown once, only its hash is stored) */
    public static function link(Agency $agency, string $role, User $inviter): array
    {
        $token = Str::random(48);
        $invite = static::create([
            'agency_id' => $agency->id,
            'email' => null,
            'via_link' => true,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addMinutes(static::LINK_VALID_MINUTES),
        ]);
        AuditLog::record('team.invite_link_created', $agency, ['role' => $role, 'invite' => $invite->id]);

        return [$invite, url('/invite/'.$token)];
    }

    public function accept(User $user): void
    {
        $agency = $this->agency;
        if ($this->via_link && $this->email === null) {
            // One use: claim the link first, so two people opening it at once cannot both join.
            $claimed = static::whereKey($this->id)->whereNull('accepted_at')->where('expires_at', '>', now())
                ->update(['accepted_at' => now(), 'email' => $user->email, 'updated_at' => now()]);
            abort_if($claimed === 0, 410);
            $this->refresh();
            AuditLog::record('team.joined_by_link', $agency, ['user_id' => $user->id, 'email' => $user->email]);
        }
        if (! $agency->members()->whereKey($user->id)->exists()) {
            $agency->members()->attach($user->id, ['role' => $this->role]);
        }
        // An owner invite (sent by a super admin for a new workspace) makes the invitee the workspace owner.
        if ($this->role === 'owner' && ! $agency->owner_id) {
            $agency->forceFill(['owner_id' => $user->id])->save();
        }
        if ($this->accepted_at === null) {
            $this->update(['accepted_at' => now()]);
        }
        AuditLog::record('team.joined', $agency, ['user_id' => $user->id, 'role' => $this->role]);
    }
}
