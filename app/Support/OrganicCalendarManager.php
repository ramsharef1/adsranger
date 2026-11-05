<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\SocialInboxMessage;
use App\Models\SocialMetricDaily;
use App\Models\SocialModerationRule;
use App\Models\SocialPost;
use Illuminate\Support\Carbon;

class OrganicCalendarManager
{
    /**
     * Groups scheduled and published posts by YYYY-MM-DD date key for a monthly calendar grid.
     *
     * @return array<string, array<int, SocialPost>>
     */
    public static function monthlyGrid(Client $client, string $yearMonth): array
    {
        $start = Carbon::parse($yearMonth . '-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $posts = SocialPost::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('scheduled_at', [$start, $end])
                  ->orWhereBetween('published_at', [$start, $end]);
            })
            ->orderBy('scheduled_at')
            ->get();

        $grid = [];
        foreach ($posts as $post) {
            $dt = $post->scheduled_at ?? $post->published_at ?? $post->created_at;
            $dayKey = $dt->format('Y-m-d');
            $grid[$dayKey][] = $post;
        }

        return $grid;
    }

    /**
     * Recommends the best day of week and time slot based on historical engagement peaks.
     *
     * @return array{best_day:string,recommended_hour:string,avg_engagements:float}
     */
    public static function recommendBestTimeToPost(Client $client, ?string $platform = null): array
    {
        $query = SocialMetricDaily::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id);

        if ($platform) {
            $query->where('platform', $platform);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [
                'best_day' => 'Thursday',
                'recommended_hour' => '19:00',
                'avg_engagements' => 0.0,
            ];
        }

        $byDay = [];
        foreach ($rows as $row) {
            $dayName = Carbon::parse($row->date)->englishDayOfWeek;
            $byDay[$dayName][] = (int) $row->engagements;
        }

        $bestDay = 'Thursday';
        $bestAvg = -1.0;
        foreach ($byDay as $day => $vals) {
            $avg = array_sum($vals) / count($vals);
            if ($avg > $bestAvg) {
                $bestAvg = $avg;
                $bestDay = $day;
            }
        }

        $recommendedHour = in_array($bestDay, ['Thursday', 'Friday', 'Saturday'], true) ? '20:00' : '18:00';

        return [
            'best_day' => $bestDay,
            'recommended_hour' => $recommendedHour,
            'avg_engagements' => round($bestAvg, 2),
        ];
    }

    /**
     * Bulk imports scheduled posts from an array of CSV rows.
     * Each row expects: title, content, platforms (comma/pipe separated), post_type, scheduled_at
     */
    public static function importCsvRows(Client $client, array $rows): int
    {
        $imported = 0;
        foreach ($rows as $row) {
            $content = trim((string) ($row['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $rawPlatforms = $row['platforms'] ?? 'meta';
            $platforms = is_array($rawPlatforms)
                ? $rawPlatforms
                : array_map('trim', preg_split('/[,|]+/', (string) $rawPlatforms));

            $scheduledAt = ! empty($row['scheduled_at']) ? Carbon::parse($row['scheduled_at']) : now()->addDay();
            $postType = (string) ($row['post_type'] ?? 'post');
            $title = isset($row['title']) ? trim((string) $row['title']) : null;

            OrganicSocialManager::schedulePost(
                $client,
                $content,
                $platforms,
                $postType,
                $scheduledAt,
                $title
            );
            $imported++;
        }

        return $imported;
    }

    /**
     * Evaluates active auto-moderation rules against an incoming unified inbox message.
     */
    public static function evaluateModerationRules(SocialInboxMessage $message): SocialInboxMessage
    {
        $rules = SocialModerationRule::query()
            ->where('agency_id', $message->agency_id)
            ->where('client_id', $message->client_id)
            ->where('is_active', true)
            ->whereIn('platform', ['all', $message->platform])
            ->get();

        $bodyLower = mb_strtolower($message->body);

        foreach ($rules as $rule) {
            $matched = false;
            if ($rule->match_type === 'sentiment_negative' && $message->sentiment === 'negative') {
                $matched = true;
            } else {
                foreach ($rule->keywords ?? [] as $kw) {
                    $kwLower = mb_strtolower(trim((string) $kw));
                    if ($kwLower !== '' && mb_stripos($bodyLower, $kwLower) !== false) {
                        $matched = true;
                        break;
                    }
                }
            }

            if ($matched) {
                $rule->increment('triggered_count');

                if ($rule->action === 'archive') {
                    $message->update(['status' => 'archived']);
                } elseif ($rule->action === 'auto_reply' && ! empty($rule->auto_reply_template)) {
                    OrganicSocialManager::replyToInboxMessage($message, $rule->auto_reply_template);
                }

                AuditLog::record('social.moderation_triggered', $message->client, [
                    'rule_id' => $rule->id,
                    'message_id' => $message->id,
                    'action' => $rule->action,
                ]);
                break;
            }
        }

        return $message->fresh();
    }
}
