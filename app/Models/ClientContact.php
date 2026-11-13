<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** XS-02 (R4, D-303): one logged call, WhatsApp chat or meeting with a client, with an optional next contact date. */
class ClientContact extends Model
{
    public const KINDS = ['call', 'whatsapp', 'meeting', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['happened_on' => 'date', 'next_on' => 'date', 'next_done_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return ['call' => __('Call'), 'whatsapp' => __('WhatsApp'), 'meeting' => __('Meeting'), 'other' => __('Other')];
    }
}
