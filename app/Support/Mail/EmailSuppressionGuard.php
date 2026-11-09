<?php

namespace App\Support\Mail;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailSuppressionGuard
{
    public static function cacheKey(string $email): string
    {
        return 'mail:suppressed:'.hash('sha256', strtolower(trim($email)));
    }

    public static function suppress(string $email, string $reason = 'hard_bounce'): void
    {
        Cache::forever(self::cacheKey($email), [
            'reason' => $reason,
            'suppressed_at' => now()->toIso8601String(),
        ]);
    }

    public static function isSuppressed(string $email): bool
    {
        return Cache::has(self::cacheKey($email));
    }

    public function handle(MessageSending $event): bool
    {
        foreach ($event->message->getTo() as $address) {
            $email = strtolower(trim($address->getAddress()));
            if (self::isSuppressed($email)) {
                Log::notice('Blocked outbound email to suppressed recipient.', [
                    'recipient_hash' => substr(hash('sha256', $email), 0, 12),
                ]);

                return false;
            }
        }

        return true;
    }
}
