<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandKit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['banned_words' => 'array'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Return any banned words found inside the given text (#134). */
    public function violations(string $text): array
    {
        $found = [];
        $lower = mb_strtolower($text);
        foreach ((array) ($this->banned_words ?? []) as $w) {
            $w = trim((string) $w);
            if ($w !== '' && mb_stripos($lower, mb_strtolower($w)) !== false) {
                $found[] = $w;
            }
        }

        return array_values(array_unique($found));
    }
}
