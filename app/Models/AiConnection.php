<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/** AI Workspace: an AI a person connected to one workspace, with the clients it may read (M1 slice 2). */
class AiConnection extends Model
{
    public const STATES = ['waiting', 'verified', 'paused', 'revoked'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['allowed_client_ids' => 'array', 'ownership_at' => 'datetime', 'verified_at' => 'datetime', 'last_call_at' => 'datetime',
            'calls_day' => 'date', 'paused_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<int> */
    public function allowedIds(): array
    {
        return array_values(array_map('intval', (array) $this->allowed_client_ids));
    }

    public function allows(int $clientId): bool
    {
        return in_array($clientId, $this->allowedIds(), true);
    }

    public function providerName(): string
    {
        return AiBrainItem::PROVIDERS[$this->provider] ?? ($this->client_name ?: 'AI');
    }

    /** Guess which AI registered, from the name it gave (DCR client_name). */
    public static function guessProvider(?string $name): ?string
    {
        $n = mb_strtolower((string) $name);

        return match (true) {
            str_contains($n, 'chatgpt') || str_contains($n, 'openai') => 'chatgpt',
            str_contains($n, 'claude') || str_contains($n, 'anthropic') => 'claude',
            str_contains($n, 'gemini') || str_contains($n, 'google') => 'gemini',
            str_contains($n, 'copilot') || str_contains($n, 'microsoft') => 'copilot',
            str_contains($n, 'perplexity') => 'perplexity',
            default => null,
        };
    }

    /** Disconnect: the token dies at once (spec v1 §2 step 6). Saved items stay. */
    public function revoke(?User $by = null): void
    {
        $this->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $by?->id]);
        $tokenIds = DB::table('oauth_access_tokens')->where('user_id', $this->user_id)->where('client_id', $this->oauth_client_id)->pluck('id');
        DB::table('oauth_access_tokens')->whereIn('id', $tokenIds)->update(['revoked' => true]);
        DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        AuditLog::record('ai_workspace.disconnected', $this, ['by' => $by?->id, 'ai' => $this->providerName()]);
    }

    public function pause(string $reason): void
    {
        if ($this->status === 'paused' || $this->status === 'revoked') {
            return;
        }
        $this->update(['status' => 'paused', 'paused_reason' => mb_substr($reason, 0, 250), 'paused_at' => now()]);
        AuditLog::record('ai_workspace.paused', $this, ['reason' => $reason, 'ai' => $this->providerName()]);
    }

    public function restore(User $by): void
    {
        $this->update(['status' => $this->verified_at ? 'verified' : 'waiting', 'paused_reason' => null, 'paused_at' => null]);
        AuditLog::record('ai_workspace.restored', $this, ['by' => $by->id]);
    }
}
