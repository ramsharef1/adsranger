<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoAuthorityLocalPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientSearchConsoleRow;
use App\Models\SeoBacklinkRecord;
use App\Models\User;
use App\Support\Seo\SafeFetcher;
use App\Support\Seo\SeoBacklinkAndDisavow;
use App\Support\Seo\SeoLocalGbpManager;
use App\Support\Seo\SeoRankTracker;
use App\Support\Seo\SeoTopicalClusterPlanner;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SeoAuthorityAndLocalTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create([
            'name' => 'Authority Co',
            'slug' => 'authority-co',
            'plan' => 'agency',
            'status' => 'active',
            'timezone' => 'Asia/Riyadh',
            'language' => 'en',
        ]);
        $owner = User::factory()->create();
        $analyst = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Jeddah Clinics',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        return [$agency, $owner, $analyst, $client];
    }

    public function test_rank_tracker_serp_contract_and_cannibalization_detector(): void
    {
        [, , , $client] = $this->workspace();

        $kw = SeoRankTracker::trackKeyword($client, 'عيادة أسنان جدة', 'ar', 'SA', 'mobile', 'https://clinic.sa/ar/dental');

        $unconf = SeoRankTracker::checkViaSerpProvider($kw, 'clinic.sa', null, null);
        $this->assertFalse($unconf['ok']);
        $this->assertSame('unconfigured', $unconf['status']);

        Http::fake([
            'https://serp-provider.example/v1/search' => Http::response([
                'serp_features' => ['local_pack', 'faq'],
                'organic' => [
                    ['position' => 1, 'link' => 'https://directory.sa/dentists'],
                    ['position' => 4, 'link' => 'https://clinic.sa/ar/dental'],
                ],
            ], 200),
        ]);

        $checked = SeoRankTracker::checkViaSerpProvider($kw, 'clinic.sa', 'https://serp-provider.example/v1/search', 'live-key');
        $this->assertTrue($checked['ok']);

        SeoRankTracker::recordSnapshot($kw, 6.0, 'https://clinic.sa/ar/blog/teeth-cleaning', 'manual', [], now()->addMinute());

        ClientSearchConsoleRow::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'site_url' => 'https://clinic.sa',
            'query' => 'عيادة اسنان جده',
            'page_url' => 'https://clinic.sa/ar/dental',
            'clicks' => 25,
            'impressions' => 500,
            'ctr' => 0.05,
            'position' => 4.2,
            'stat_date' => '2026-11-12',
        ]);
        ClientSearchConsoleRow::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'site_url' => 'https://clinic.sa',
            'query' => 'عيادة أسنان جدة',
            'page_url' => 'https://clinic.sa/ar/blog/teeth-cleaning',
            'clicks' => 12,
            'impressions' => 300,
            'ctr' => 0.04,
            'position' => 6.8,
            'stat_date' => '2026-11-12',
        ]);

        $cannibal = SeoRankTracker::detectCannibalization($client);
        $this->assertCount(1, $cannibal);
        $this->assertSame('impression_split_and_url_swap', $cannibal[0]['reason']);
        $this->assertGreaterThanOrEqual(1, $cannibal[0]['url_swaps']);
        $this->assertCount(2, $cannibal[0]['competing_urls']);
    }

    public function test_topical_silos_and_hreflang_pair_verification(): void
    {
        [, , , $client] = $this->workspace();

        $cluster = SeoTopicalClusterPlanner::saveCluster(
            $client,
            'زراعة الأسنان في السعودية',
            'https://clinic.sa/ar/implants',
            [
                ['title' => 'تكلفة زراعة الأسنان', 'url' => 'https://clinic.sa/ar/implant-cost', 'locale' => 'ar', 'status' => 'live'],
                ['title' => 'Dental implant cost in KSA', 'url' => 'https://clinic.sa/en/implant-cost', 'locale' => 'en', 'status' => 'planned'],
            ],
            ['زراعة الأسنان الفورية', 'dental implants jeddah'],
            'Pillar guide linking to regional pricing spokes.'
        );

        $this->assertSame('paired_planned', $cluster->hreflang_pair_status);
        $this->assertCount(2, $cluster->spokes);

        SafeFetcher::$resolver = fn (string $host): string => '93.184.216.34';
        Http::fake([
            'https://clinic.sa/ar/implants' => Http::response(
                '<html lang="ar"><head>'
                .'<link rel="alternate" hreflang="ar-SA" href="https://clinic.sa/ar/implants">'
                .'<link rel="alternate" hreflang="en-SA" href="https://clinic.sa/en/implants">'
                .'</head><body><h1>زراعة الأسنان</h1></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
        ]);

        try {
            $verified = SeoTopicalClusterPlanner::verifyPillarHreflang($cluster);
        } finally {
            SafeFetcher::$resolver = null;
        }

        $this->assertSame('verified_ar_en', $verified);
    }

    public function test_backlink_csv_competitor_gap_and_valid_utf8_google_disavow_export(): void
    {
        [, , , $client] = $this->workspace();

        $csv = "source_url,target_url,anchor_text,link_status\n"
            ."https://health-journal.sa/dentists,https://clinic.sa/ar/dental,عيادة جدة,active\n"
            ."https://low-quality-links.example/dir1,https://clinic.sa/,cheap links,active\n";

        $imported = SeoBacklinkAndDisavow::importGscLinksCsv($client, $csv);
        $this->assertSame(2, $imported);

        $gaps = SeoBacklinkAndDisavow::competitorDomainGap($client, [
            'rival-one.sa' => ['https://health-journal.sa/top', 'https://med-news.ae/feature', 'https://gulf-care.com/list'],
            'rival-two.sa' => ['https://med-news.ae/interview', 'https://riyadh-life.sa/post'],
        ], 2);

        $this->assertCount(1, $gaps);
        $this->assertSame('med-news.ae', $gaps[0]['referring_domain']);
        $this->assertSame(2, $gaps[0]['competitor_count']);

        $spam = SeoBacklinkRecord::where('client_id', $client->id)
            ->where('source_domain', 'low-quality-links.example')
            ->firstOrFail();
        $spam->update(['is_flagged_disavow' => true]);

        $disavow = SeoBacklinkAndDisavow::exportDisavowTxt($client, 'November Disavow', true);
        $this->assertTrue(mb_check_encoding($disavow->exported_txt, 'UTF-8'));
        $this->assertStringContainsString("domain:low-quality-links.example\n", $disavow->exported_txt);
        $this->assertStringNotContainsString('health-journal.sa', $disavow->exported_txt);
    }

    public function test_local_gbp_nap_rtl_audit_review_sync_and_module_gated_page(): void
    {
        [$agency, $owner, $analyst, $client] = $this->workspace();

        $badLoc = SeoLocalGbpManager::saveLocation($client, [
            'name_ar' => 'فرع التحلية',
            'name_en' => '',
            'phone_e164' => '+٩٦٦ ٥٠ ١٢٣ ٤٥٦٧',
            'address_ar' => 'شارع التحلية، جدة',
            'address_en' => 'Tahlia St, Jeddah',
            'hours_json' => ['sun' => '٠٩:٠٠-٢٢:٠٠', 'mon' => '22:00-09:00'],
        ]);

        $codes = array_column($badLoc->nap_issues, 'code');
        $this->assertContains('rtl_phone_digits_or_bidi', $codes);
        $this->assertContains('missing_bilingual_name', $codes);
        $this->assertContains('rtl_hours_digits', $codes);
        $this->assertContains('inverted_hours_range', $codes);
        $this->assertSame('+966501234567', $badLoc->phone_e164);

        $cleanLoc = SeoLocalGbpManager::saveLocation($client, [
            'name_ar' => 'فرع الروضة',
            'name_en' => 'Al Rawdah Branch',
            'phone_e164' => '+966126543210',
            'address_ar' => 'حي الروضة، جدة',
            'address_en' => 'Al Rawdah Dist, Jeddah',
            'hours_json' => ['sun' => '09:00-22:00'],
            'gbp_location_id' => 'accounts/1/locations/99',
        ]);
        $this->assertSame([], $cleanLoc->nap_issues);

        $unconf = SeoLocalGbpManager::syncGbpReviews($cleanLoc, '');
        $this->assertSame('unconfigured', $unconf['status']);

        Http::fake([
            'mybusiness.googleapis.com/*' => Http::response([
                'reviews' => [
                    ['reviewId' => 'r1', 'reviewer' => ['displayName' => 'سارة'], 'starRating' => 'FIVE', 'comment' => 'خدمة ممتازة'],
                    ['reviewId' => 'r2', 'reviewer' => ['displayName' => 'Omar'], 'starRating' => 'TWO', 'comment' => 'Long wait'],
                ],
            ], 200),
        ]);

        $synced = SeoLocalGbpManager::syncGbpReviews($cleanLoc, 'gbp-tok');
        $this->assertTrue($synced['ok']);
        $this->assertSame(2, $synced['synced_count']);
        $this->assertStringContainsString('مسودة للرد:', $cleanLoc->fresh()->reviews_json[0]['draft_ar']);
        $this->assertStringContainsString('Draft reply:', $cleanLoc->fresh()->reviews_json[1]['draft_en']);

        $this->actingAs($owner)->get("/app/{$agency->slug}/seo-authority-local")->assertForbidden();

        config(['modules.preview_agencies' => [$agency->slug]]);
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/seo-authority-local")
            ->assertOk()
            ->assertSee('Ranks, authority & local SEO');

        $this->actingAs($analyst);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(SeoAuthorityLocalPage::class)->call('addTrackedKeyword')->assertForbidden();
    }
}
