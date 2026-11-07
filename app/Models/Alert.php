<?php

namespace App\Models;

use App\Alerts\AlertText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'snoozed_until' => 'datetime', 'resolved_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** UX-121: title in the reader's language (stored text is English; see AlertText). */
    public function localTitle(?string $locale = null): string
    {
        return AlertText::title($this->title, $locale);
    }

    public function localDetail(?string $locale = null): ?string
    {
        return $this->detail === null ? null : AlertText::detail($this->detail, $locale);
    }
}
