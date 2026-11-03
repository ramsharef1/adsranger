<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        ];
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
