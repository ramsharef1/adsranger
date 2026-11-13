<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** AI Workspace: one saved item in a client's brain «ذاكرة العميل» (M1 item 3). */
class AiBrainItem extends Model
{
    use SoftDeletes;

    public const TYPES = ['rule', 'decision', 'brief', 'learning', 'output', 'chat_link'];

    public const PROVIDERS = ['chatgpt' => 'ChatGPT', 'claude' => 'Claude', 'gemini' => 'Gemini', 'copilot' => 'Copilot', 'perplexity' => 'Perplexity'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['output' => 'array', 'checks' => 'array', 'cleanup' => 'array', 'blocking' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerName(): ?string
    {
        return self::PROVIDERS[$this->ai_provider] ?? null;
    }
}
