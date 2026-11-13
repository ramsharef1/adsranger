<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** EH-17 (D-293): one announcement line at a time for every workspace, with an end date; each user can close it. */
class Announcements
{
    public static function current(): ?object
    {
        return once(function (): ?object {
            try {
                if (! Schema::hasTable('announcements')) {
                    return null;
                }

                return DB::table('announcements')->where('starts_at', '<=', now())->where('ends_at', '>', now())->orderByDesc('id')->first();
            } catch (\Throwable) {
                return null;
            }
        });
    }

    public static function post(string $bodyAr, string $bodyEn, string $tone, int $days, ?int $userId): int
    {
        // One at a time: a new line ends the one before it.
        DB::table('announcements')->where('ends_at', '>', now())->update(['ends_at' => now(), 'updated_at' => now()]);

        return DB::table('announcements')->insertGetId([
            'body_ar' => mb_substr(trim($bodyAr), 0, 240), 'body_en' => mb_substr(trim($bodyEn), 0, 240),
            'tone' => $tone === 'warning' ? 'warning' : 'info', 'starts_at' => now(), 'ends_at' => now()->addDays(max(1, min(30, $days))),
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function end(): void
    {
        DB::table('announcements')->where('ends_at', '>', now())->update(['ends_at' => now(), 'updated_at' => now()]);
    }
}
