<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Support\PlanGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedPlanMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function agencyOn(string $plan, string $status = 'active'): Agency
    {
        return Agency::create([
            'name' => 'Matrix '.$plan.' '.$status,
            'slug' => Agency::uniqueSlug('matrix-'.$plan.'-'.$status),
            'plan' => $plan,
            'status' => $status,
        ]);
    }

    public function test_free_starter_pro_and_agency_tiers_unlock_exact_seo_sem_mena_and_night_shift_capabilities(): void
    {
        $free = $this->agencyOn('free');
        $starter = $this->agencyOn('starter');
        $pro = $this->agencyOn('pro');
        $agency = $this->agencyOn('agency');

        // Free tier: 1 watched ad URL & 1-site GSC top-10 snapshot; Starter+ locked
        foreach (['gsc_top10_snapshot', 'watched_urls_basic'] as $freeKey) {
            $this->assertTrue(PlanGate::allows($free, $freeKey), $freeKey);
            $this->assertTrue(PlanGate::allows($starter, $freeKey), $freeKey);
        }
        $this->assertFalse(PlanGate::allows($free, 'seo_gsc_ga4_sync'));
        $this->assertFalse(PlanGate::allows($free, 'sem_root_cause_investigator'));

        // Starter tier: GSC + GA4 sync & campaign Root-Cause Investigator; Pro+ locked
        foreach (['seo_gsc_ga4_sync', 'sem_root_cause_investigator'] as $starterKey) {
            $this->assertTrue(PlanGate::allows($starter, $starterKey), $starterKey);
            $this->assertTrue(PlanGate::allows($pro, $starterKey), $starterKey);
        }
        $proKeys = [
            'seo_log_analyzer',
            'seo_rank_tracker',
            'seo_topical_planner',
            'seo_local_gbp',
            'sem_ngram_miner',
            'sem_shopping_feeds',
            'sem_rule_engine',
            'sem_cohort_ltv',
            'mena_creator_poas',
            'mena_offline_conversions',
            'ai_night_shift_queue',
            'prospect_pitch_decks',
        ];
        foreach ($proKeys as $proKey) {
            $this->assertFalse(PlanGate::allows($starter, $proKey), "starter should lock $proKey");
            $this->assertTrue(PlanGate::allows($pro, $proKey), "pro should unlock $proKey");
            $this->assertTrue(PlanGate::allows($agency, $proKey), "agency should unlock $proKey");
        }

        // Agency tier: 500-kw Rank Tracker, Backlink Gap & Disavow, Multi-Branch Local SEO,
        // 50k-SKU Shopping Feeds, Cross-Account Rule Templates, Unlimited Pitch Decks, Retainer Split
        $agencyKeys = [
            'seo_rank_tracker_500',
            'seo_backlink_disavow',
            'seo_local_multi_branch',
            'sem_shopping_feeds_50k',
            'sem_rule_templates',
            'prospect_pitch_decks_unlimited',
            'retainer_split',
        ];
        foreach ($agencyKeys as $agencyKey) {
            $this->assertFalse(PlanGate::allows($pro, $agencyKey), "pro should lock $agencyKey");
            $this->assertTrue(PlanGate::allows($agency, $agencyKey), "agency should unlock $agencyKey");
        }
    }

    public function test_numeric_quotas_scale_cleanly_across_free_starter_pro_agency_and_trial(): void
    {
        $free = $this->agencyOn('free');
        $starter = $this->agencyOn('starter');
        $trial = $this->agencyOn('starter', 'trial');
        $pro = $this->agencyOn('pro');
        $agency = $this->agencyOn('agency');

        // Watched ad URLs: Free=1, Starter=10, Pro=50, Agency=250
        $this->assertSame(1, PlanGate::quota($free, 'watched_urls'));
        $this->assertSame(10, PlanGate::quota($starter, 'watched_urls'));
        $this->assertSame(50, PlanGate::quota($pro, 'watched_urls'));
        $this->assertSame(50, PlanGate::quota($trial, 'watched_urls'));
        $this->assertSame(250, PlanGate::quota($agency, 'watched_urls'));
        $this->assertTrue(PlanGate::withinQuota($free, 'watched_urls', 0));
        $this->assertFalse(PlanGate::withinQuota($free, 'watched_urls', 1));

        // GSC top-10 snapshot on Free vs full rows on Starter+
        $this->assertSame(1, PlanGate::quota($free, 'gsc_sites'));
        $this->assertSame(10, PlanGate::quota($free, 'gsc_snapshot_rows'));

        // Rank tracker keywords: Pro=100, Agency=500
        $this->assertSame(0, PlanGate::quota($starter, 'tracked_keywords'));
        $this->assertSame(100, PlanGate::quota($pro, 'tracked_keywords'));
        $this->assertSame(500, PlanGate::quota($agency, 'tracked_keywords'));

        // Local SEO branches: Pro=3, Agency=null (unlimited under fair use)
        $this->assertSame(3, PlanGate::quota($pro, 'local_seo_branches'));
        $this->assertNull(PlanGate::quota($agency, 'local_seo_branches'));
        $this->assertTrue(PlanGate::withinQuota($agency, 'local_seo_branches', 999));

        // Shopping feed SKUs: Pro=5,000, Agency=50,000
        $this->assertSame(5000, PlanGate::quota($pro, 'shopping_feed_skus'));
        $this->assertSame(50000, PlanGate::quota($agency, 'shopping_feed_skus'));

        // Pitch decks per month: Pro=2, Agency=null (unlimited under fair use)
        $this->assertSame(0, PlanGate::quota($starter, 'pitch_decks_per_month'));
        $this->assertSame(2, PlanGate::quota($pro, 'pitch_decks_per_month'));
        $this->assertFalse(PlanGate::withinQuota($pro, 'pitch_decks_per_month', 2));
        $this->assertNull(PlanGate::quota($agency, 'pitch_decks_per_month'));
        $this->assertTrue(PlanGate::withinQuota($agency, 'pitch_decks_per_month', 50));

        // Free stays excluded from offered paid tier list on Settings > Plan
        $this->assertNotContains('free', array_keys(PlanGate::byTier()));
    }
}
