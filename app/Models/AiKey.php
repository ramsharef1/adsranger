<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AI Workspace: an agency's own API key for one provider (#159 vault, spec v1 §2 step 5). The key is never shown again. */
class AiKey extends Model
{
    public const PROVIDERS = ['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Google Gemini'];

    /** Which assistant label a key's output carries on the clean card. */
    public const AS_ASSISTANT = ['openai' => 'chatgpt', 'anthropic' => 'claude', 'gemini' => 'gemini'];

    protected $guarded = [];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'monthly_cap_usd' => 'decimal:2', 'spent_usd' => 'decimal:4', 'tested_at' => 'datetime', 'test_ok' => 'boolean'];
    }

    public function spentThisMonth(): float
    {
        return $this->spent_month === now()->format('Y-m') ? (float) $this->spent_usd : 0.0;
    }

    public function remaining(): float
    {
        return max(0.0, (float) $this->monthly_cap_usd - $this->spentThisMonth());
    }

    public function addSpend(float $usd): void
    {
        $month = now()->format('Y-m');
        $this->forceFill(['spent_usd' => ($this->spent_month === $month ? (float) $this->spent_usd : 0.0) + $usd, 'spent_month' => $month])->save();
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
