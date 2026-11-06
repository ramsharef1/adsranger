<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Support\LaunchReadinessAuditor;
use App\Support\PsychologicalTriggerAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave12PsychologicalHookScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_psychological_trigger_and_hook_scoring_generates_mena_dialect_variants(): void
    {
        $agency = Agency::create(['name' => 'Riyadh Creative Lab', 'slug' => 'riyadh-lab', 'plan' => 'pro', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Saudi Luxury Perfumes', 'currency' => 'SAR']);

        $result = PsychologicalTriggerAnalyzer::analyzeAndScore(
            $client,
            'احصل الآن على عطر العود الحصري مع ضمان معتمد لأفضل تألق',
            'gulf'
        );

        $this->assertSame($agency->id, $result->agency_id);
        $this->assertGreaterThanOrEqual(70, $result->overall_score);
        $this->assertGreaterThanOrEqual(70, $result->trigger_scores['urgency']);
        $this->assertGreaterThanOrEqual(70, $result->trigger_scores['fomo']);
        $this->assertArrayHasKey('gulf', $result->dialect_variants);
        $this->assertArrayHasKey('levantine', $result->dialect_variants);
        $this->assertArrayHasKey('egyptian', $result->dialect_variants);

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(16, $audit['tenant_tables_verified']);
    }
}