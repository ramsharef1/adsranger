<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\SocialInboxMessage;
use App\Models\SocialMetricDaily;
use App\Models\SocialModerationRule;
use App\Support\OrganicCalendarManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave6OrganicCalendarModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_csv_import_and_monthly_calendar_grid(): void
    {
        $agency = Agency::create(['name' => 'Calendar Agency', 'slug' => 'calendar-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => ' Jordan Retail', 'currency' => 'JOD', 'status' => 'active']);

        $count = OrganicCalendarManager::importCsvRows($client, [
            [
                'title' => 'Ramadan Teaser 1',
                'content' => 'Stay tuned for our Ramadan collection!',
                'platforms' => 'meta,tiktok',
                'post_type' => 'reel',
                'scheduled_at' => '2026-11-15 18:00:00',
            ],
            [
                'title' => 'Weekend Offer',
                'content' => '20% off in all Amman branches.',
                'platforms' => 'meta|snapchat',
                'post_type' => 'post',
                'scheduled_at' => '2026-11-20 20:00:00',
            ],
        ]);

        $this->assertSame(2, $count);

        $grid = OrganicCalendarManager::monthlyGrid($client, '2026-11');
        $this->assertArrayHasKey('2026-11-15', $grid);
        $this->assertArrayHasKey('2026-11-20', $grid);
        $this->assertSame('Ramadan Teaser 1', $grid['2026-11-15'][0]->title);
    }

    public function test_best_time_to_post_recommendation_from_daily_metrics(): void
    {
        $agency = Agency::create(['name' => 'Calendar Agency', 'slug' => 'calendar-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Jordan Retail', 'currency' => 'JOD', 'status' => 'active']);

        // 2026-11-06 is a Friday
        SocialMetricDaily::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'platform' => 'meta',
            'date' => '2026-11-06',
            'followers_count' => 10000,
            'followers_gained' => 90,
            'impressions' => 15000,
            'reach' => 9000,
            'engagements' => 1200,
            'profile_views' => 300,
        ]);

        // 2026-11-03 is a Tuesday
        SocialMetricDaily::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'platform' => 'meta',
            'date' => '2026-11-03',
            'followers_count' => 9910,
            'followers_gained' => 20,
            'impressions' => 5000,
            'reach' => 3000,
            'engagements' => 200,
            'profile_views' => 80,
        ]);

        $rec = OrganicCalendarManager::recommendBestTimeToPost($client, 'meta');
        $this->assertSame('Friday', $rec['best_day']);
        $this->assertSame('20:00', $rec['recommended_hour']);
        $this->assertSame(1200.0, $rec['avg_engagements']);
    }

    public function test_social_moderation_rules_auto_archive_and_auto_reply(): void
    {
        $agency = Agency::create(['name' => 'Calendar Agency', 'slug' => 'calendar-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Jordan Retail', 'currency' => 'JOD', 'status' => 'active']);

        SocialModerationRule::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'name' => 'Hide Crypto Spam',
            'platform' => 'all',
            'keywords' => ['crypto', 'free bitcoin', 'ربح سريع'],
            'match_type' => 'contains',
            'action' => 'archive',
            'is_active' => true,
        ]);

        SocialModerationRule::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'name' => 'Price Inquiry Auto-Reply',
            'platform' => 'meta',
            'keywords' => ['كم السعر', 'بكم', 'price'],
            'match_type' => 'contains',
            'action' => 'auto_reply',
            'auto_reply_template' => 'تم إرسال قائمة الأسعار على الخاص!',
            'is_active' => true,
        ]);

        $spamMsg = SocialInboxMessage::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'platform' => 'tiktok',
            'message_type' => 'comment',
            'sender_name' => 'SpamBot',
            'body' => 'Click here for free bitcoin and crypto!',
            'sentiment' => 'neutral',
            'status' => 'open',
        ]);

        $moderatedSpam = OrganicCalendarManager::evaluateModerationRules($spamMsg);
        $this->assertSame('archived', $moderatedSpam->status);

        $priceMsg = SocialInboxMessage::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'platform' => 'meta',
            'message_type' => 'comment',
            'sender_name' => 'Sara',
            'body' => 'لو سمحت كم السعر؟',
            'sentiment' => 'neutral',
            'status' => 'open',
        ]);

        $moderatedPrice = OrganicCalendarManager::evaluateModerationRules($priceMsg);
        $this->assertSame('replied', $moderatedPrice->status);
        $this->assertSame('تم إرسال قائمة الأسعار على الخاص!', $moderatedPrice->reply_body);
    }
}
