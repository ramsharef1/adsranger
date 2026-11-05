<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\SocialInboxMessage;
use App\Models\SocialMetricDaily;
use App\Support\OrganicSocialManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave5OrganicSocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_schedule_and_publish_multi_platform_organic_posts(): void
    {
        $agency = Agency::create(['name' => 'Organic Agency', 'slug' => 'organic-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Cafe Amman', 'currency' => 'JOD', 'status' => 'active']);

        $scheduled = OrganicSocialManager::schedulePost(
            $client,
            'Join us this weekend for specialty coffee in Amman!',
            ['meta', 'tiktok', 'snapchat'],
            'reel',
            now()->addDays(2),
            'Weekend Coffee Reel'
        );

        $this->assertSame('scheduled', $scheduled->status);
        $this->assertEquals(['meta', 'tiktok', 'snapchat'], $scheduled->platforms);

        $published = OrganicSocialManager::schedulePost(
            $client,
            'Fresh pastries out of the oven now.',
            ['meta', 'x'],
            'post'
        );

        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->published_at);
    }

    public function test_unified_inbox_sentiment_and_reply_workflow(): void
    {
        $agency = Agency::create(['name' => 'Organic Agency', 'slug' => 'organic-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Cafe Amman', 'currency' => 'JOD', 'status' => 'active']);

        $this->assertSame('positive', OrganicSocialManager::detectSentiment('خدمة ممتازة وقهوة رائعة شكرا لكم'));
        $this->assertSame('negative', OrganicSocialManager::detectSentiment('الطلب بطيء جدا وسيء'));

        $msg = SocialInboxMessage::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'platform' => 'meta',
            'message_type' => 'comment',
            'sender_name' => 'Ahmad',
            'sender_handle' => '@ahmad_jo',
            'body' => 'متى تفتحون يوم الجمعة؟',
            'sentiment' => OrganicSocialManager::detectSentiment('متى تفتحون يوم الجمعة؟'),
            'status' => 'open',
        ]);

        $updated = OrganicSocialManager::replyToInboxMessage($msg, 'نفتح من الساعة 8 صباحاً، أهلاً بك!');
        $this->assertSame('replied', $updated->status);
        $this->assertNotNull($updated->replied_at);
        $this->assertSame('نفتح من الساعة 8 صباحاً، أهلاً بك!', $updated->reply_body);
    }

    public function test_audience_growth_and_engagement_analytics_summary(): void
    {
        $agency = Agency::create(['name' => 'Organic Agency', 'slug' => 'organic-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Cafe Amman', 'currency' => 'JOD', 'status' => 'active']);

        SocialMetricDaily::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'platform' => 'meta',
            'date' => '2026-11-01',
            'followers_count' => 12000,
            'followers_gained' => 150,
            'impressions' => 8000,
            'reach' => 5000,
            'engagements' => 400,
            'profile_views' => 320,
        ]);

        SocialMetricDaily::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'platform' => 'tiktok',
            'date' => '2026-11-02',
            'followers_count' => 8500,
            'followers_gained' => 250,
            'impressions' => 12000,
            'reach' => 5000,
            'engagements' => 600,
            'profile_views' => 410,
        ]);

        $summary = OrganicSocialManager::engagementSummary($client, '2026-11-01', '2026-11-05');
        $this->assertSame(400, $summary['followers_gained']);
        $this->assertSame(20000, $summary['impressions']);
        $this->assertSame(10000, $summary['reach']);
        $this->assertSame(1000, $summary['engagements']);
        $this->assertSame(10.0, $summary['engagement_rate']);
    }
}
