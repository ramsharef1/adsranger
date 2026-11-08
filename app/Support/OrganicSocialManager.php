<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\SocialInboxMessage;
use App\Models\SocialMetricDaily;
use App\Models\SocialPost;

class OrganicSocialManager
{
    public const SUPPORTED_PLATFORMS = ['meta', 'tiktok', 'x', 'linkedin', 'snapchat'];

    public static function schedulePost(
        Client $client,
        string $content,
        array $platforms,
        string $postType = 'post',
        ?\DateTimeInterface $scheduledAt = null,
        ?string $title = null,
        array $mediaUrls = []
    ): SocialPost {
        $validPlatforms = array_values(array_intersect($platforms, self::SUPPORTED_PLATFORMS));
        $status = $scheduledAt ? 'scheduled' : 'simulated'; // D-229: no platform post is made yet

        $post = SocialPost::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'title' => $title,
            'content' => $content,
            'platforms' => $validPlatforms,
            'post_type' => $postType,
            'media_urls' => $mediaUrls,
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'published_at' => null,
        ]);

        AuditLog::record('social.post_'.$status, $client, [
            'post_id' => $post->id,
            'platforms' => $validPlatforms,
        ]);

        return $post;
    }

    public static function detectSentiment(string $text): string
    {
        $lower = mb_strtolower($text);
        $positive = ['great', 'love', 'awesome', 'thank', 'excellent', 'ممتاز', 'رائع', 'شكرا', 'جميل'];
        $negative = ['bad', 'terrible', 'worst', 'scam', 'slow', 'سيء', 'مشكلة', 'بطيء', 'غالي'];

        foreach ($negative as $word) {
            if (mb_stripos($lower, $word) !== false) {
                return 'negative';
            }
        }
        foreach ($positive as $word) {
            if (mb_stripos($lower, $word) !== false) {
                return 'positive';
            }
        }

        return 'neutral';
    }

    public static function replyToInboxMessage(SocialInboxMessage $message, string $replyBody): SocialInboxMessage
    {
        $message->update([
            'reply_body' => $replyBody,
            'status' => 'simulated', // D-229: the reply is stored, not sent to the platform
            'replied_at' => null,
        ]);

        AuditLog::record('social.inbox_replied', $message->client, [
            'message_id' => $message->id,
            'platform' => $message->platform,
        ]);

        return $message->fresh();
    }

    /** @return array{followers_gained:int,impressions:int,reach:int,engagements:int,engagement_rate:float} */
    public static function engagementSummary(Client $client, string $from, string $to): array
    {
        $rows = SocialMetricDaily::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();

        $gained = (int) $rows->sum('followers_gained');
        $impressions = (int) $rows->sum('impressions');
        $reach = (int) $rows->sum('reach');
        $engagements = (int) $rows->sum('engagements');
        $rate = $reach > 0 ? round(($engagements / $reach) * 100, 2) : 0.0;

        return [
            'followers_gained' => $gained,
            'impressions' => $impressions,
            'reach' => $reach,
            'engagements' => $engagements,
            'engagement_rate' => $rate,
        ];
    }
}
