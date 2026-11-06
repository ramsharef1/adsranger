<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Support\KeywordMappingManager;
use App\Support\LaunchReadinessAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave11KeywordMappingMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_intent_and_keyword_mapping_matrix_generates_arabic_and_english_ad_groups(): void
    {
        $agency = Agency::create(['name' => 'Amman Search Lab', 'slug' => 'amman-search', 'plan' => 'pro', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Jordan Dental Clinic', 'currency' => 'JOD']);

        $arMap = KeywordMappingManager::generateForClient($client, 'زراعة الأسنان', 'ar');

        $this->assertSame($agency->id, $arMap->agency_id);
        $this->assertCount(3, $arMap->intent_buckets['transactional']);
        $this->assertNotEmpty($arMap->ad_group_structures['google_ads']);
        $this->assertNotEmpty($arMap->ad_group_structures['microsoft_ads']);
        $this->assertContains('مجانا', $arMap->negative_keywords);

        $enMap = KeywordMappingManager::generateForClient($client, 'Dental Implants', 'en');
        $this->assertContains('[buy Dental Implants]', $enMap->ad_group_structures['google_ads'][0]['keywords']);

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(15, $audit['tenant_tables_verified']);
    }
}