<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One platform-wide setting (key → JSON value), changed by a super admin in /admin › Platform settings. */
class PlatformSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        return $row ? $row->value : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
