<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** E2-065: one client's brand (Arabic + English name), main domain and the questions to probe. */
class AeoBrandMonitor extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tracked_prompts' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(AeoCitationCheck::class, 'monitor_id');
    }

    public function latest(string $source): ?AeoCitationCheck
    {
        return $this->checks()->where('source', $source)->latest('checked_at')->latest('id')->first();
    }
}
