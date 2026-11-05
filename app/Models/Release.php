<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * #76 In-app What's new release note (`releases` table, spec.md line 94).
 */
class Release extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'items' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->orderByDesc('date')->orderByDesc('id');
    }

    public function title(?string $locale = null): string
    {
        $loc = $locale ?? app()->getLocale();

        return $loc === 'ar'
            ? (trim((string) $this->title_ar) !== '' ? $this->title_ar : $this->title_en)
            : (trim((string) $this->title_en) !== '' ? $this->title_en : $this->title_ar);
    }

    /**
     * Normalised bullet lines for the active locale.
     * Supports either `['en' => [...], 'ar' => [...]]` or a list of `['en' => '...', 'ar' => '...']` rows.
     *
     * @return list<string>
     */
    public function itemsForLocale(?string $locale = null): array
    {
        $loc = $locale ?? app()->getLocale();
        $raw = is_array($this->items) ? $this->items : [];

        if (isset($raw[$loc]) && is_array($raw[$loc])) {
            return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $raw[$loc]), fn ($v) => $v !== ''));
        }

        $out = [];
        foreach ($raw as $row) {
            if (is_string($row) && trim($row) !== '') {
                $out[] = trim($row);
            } elseif (is_array($row)) {
                $text = $loc === 'ar'
                    ? (trim((string) ($row['ar'] ?? '')) !== '' ? $row['ar'] : ($row['en'] ?? ''))
                    : (trim((string) ($row['en'] ?? '')) !== '' ? $row['en'] : ($row['ar'] ?? ''));
                if (trim((string) $text) !== '') {
                    $out[] = trim((string) $text);
                }
            }
        }

        return $out;
    }

    /**
     * Published releases from the DB (newest first).
     *
     * @return Collection<int, self>
     */
    public static function feed(): Collection
    {
        return static::query()->published()->limit(50)->get();
    }
}
