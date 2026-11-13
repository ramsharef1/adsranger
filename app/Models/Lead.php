<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** XS-13 / XS-23 (R4, D-306): one lead or WhatsApp chat: when it came in, when someone answered, and how it ended. */
class Lead extends Model
{
    public const CHANNELS = ['whatsapp', 'call', 'form', 'walk_in', 'other'];

    public const OUTCOMES = ['qualified', 'sale', 'not_relevant'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'contacted_at' => 'datetime', 'value' => 'float'];
    }
}
