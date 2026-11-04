<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdNetwork extends Model
{
    public const STATUSES = [
        'not_set' => 'Not set',
        'testing' => 'Testing',
        'live' => 'Approved / Live',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'credentials_live' => 'encrypted:array',
            'credentials_sandbox' => 'encrypted:array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    /** Networks that reuse another network's OAuth client when their own fields are empty. */
    public const REUSES = ['ga4' => 'google_ads', 'google_merchant' => 'google_ads'];

    /** The key set in use (live or sandbox), with fallback to the parent network's keys. */
    public function credentials(?string $env = null): array
    {
        $env = $env ?: $this->environment;
        $own = array_filter((array) ($this->{"credentials_{$env}"} ?? []));
        if (isset(self::REUSES[$this->key]) && empty($own['client_id'])) {
            $parent = static::where('key', self::REUSES[$this->key])->first();
            $own = array_filter((array) ($parent?->{"credentials_{$env}"} ?? [])) + $own;
        }

        return $own;
    }

    public function hasKeys(?string $env = null): bool
    {
        $c = $this->credentials($env);
        $first = collect($this->definition()['fields'] ?? [])->first()[0] ?? null;

        return $first === null || ! empty($c[$first]) || ! empty($c['client_id']) || ! empty($c['app_id']) || ! empty($c['api_key']);
    }

    /** Catalogue entry from config/ad_networks.php (name, steps, fields...). */
    public function definition(): array
    {
        return config('ad_networks.'.$this->key, []);
    }

    public function secretKeys(): array
    {
        return collect($this->definition()['fields'] ?? [])
            ->filter(fn (array $f) => $f[2])
            ->map(fn (array $f) => $f[0])
            ->values()
            ->all();
    }

    protected static function booted(): void
    {
        // Audit every change. Only field NAMES are logged, never key values.
        static::updated(function (AdNetwork $network) {
            $changed = [];

            foreach (['credentials_live', 'credentials_sandbox'] as $column) {
                if (! $network->wasChanged($column)) {
                    continue;
                }
                $old = (array) ($network->getOriginal($column) ?? []);
                $new = (array) ($network->{$column} ?? []);
                foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field) {
                    if (($old[$field] ?? null) !== ($new[$field] ?? null)) {
                        $changed[] = $column.'.'.$field;
                    }
                }
            }

            foreach (array_keys($network->getChanges()) as $attribute) {
                if (! in_array($attribute, ['updated_at', 'credentials_live', 'credentials_sandbox'], true)) {
                    $changed[] = $attribute;
                }
            }

            if ($changed) {
                AuditLog::record('ad_network.updated', $network, ['network' => $network->key, 'changed' => $changed]);
            }
        });
    }
}
