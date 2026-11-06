<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Support\LaunchReadinessAuditor;
use App\Support\NetworkSpecificAccelerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave16NetworkAcceleratorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_network_specific_accelerators_meta_capi_tiktok_spark_snap_ar_and_linkedin_abm(): void
    {
        $agency = Agency::create(['name' => 'GCC Omnichannel Hub', 'slug' => 'gcc-omni', 'plan' => 'agency', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Gulf Enterprise Group', 'currency' => 'AED']);

        $meta = NetworkSpecificAccelerator::syncMetaLeadToCapi($client, 'lead_991', 4500.0, 'closed_won');
        $this->assertSame('capi_dispatched', $meta->status);
        $this->assertEquals(4500.0, $meta->attributed_value);

        $tiktok = NetworkSpecificAccelerator::boostTikTokSparkAd($client, 'post_772', 8.4);
        $this->assertSame('syndicated_to_paid', $tiktok->status);
        $this->assertStringStartsWith('SPARK-', $tiktok->payload['spark_auth_code']);

        $snap = NetworkSpecificAccelerator::trackSnapchatArLensRoi($client, 'lens_ramadan_1', 650, 13000.0);
        $this->assertEquals(20.0, $snap->payload['revenue_per_swipe']);

        $li = NetworkSpecificAccelerator::scoreLinkedInB2bLead($client, 'Riyadh Cloud Tech', 450, 'fintech');
        $this->assertSame(100, $li->score);
        $this->assertSame('hot_enterprise_account', $li->status);

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(20, $audit['tenant_tables_verified']);
    }
}