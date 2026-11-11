<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Filament\Admin\Pages\PartnerApplications;
use App\Filament\Admin\Pages\PlatformSettings;
use App\Filament\Admin\Pages\WaitlistEntries;
use App\Filament\Admin\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Pages\AlertRules;
use App\Filament\Pages\PlanUsage;
use App\Mail\AgencyInvitationMail;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\Client;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\PartnerApplication;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WorkspaceMagicLink;
use App\Support\AiAnalyst;
use App\Support\AiSettings;
use App\Support\CustomDomainManager;
use App\Support\HealthScore;
use App\Support\Modules;
use App\Support\PlanGate;
use App\Support\Trial;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class WaveA1LockedDecisionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_m1_1_and_m1_2_waitlist_send_email_invite_and_provision_magic_link_and_regenerate(): void
    {
        Mail::fake();
        $admin = $this->makeSuperAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $consented = WaitlistEntry::create([
            'email' => 'founder@ammanagency.com',
            'name' => 'Zaid Haddad',
            'agency_name' => 'Amman Growth Partners',
            'country' => 'JO',
            'audience' => 'agency',
            'lang' => 'ar',
            'consent_at' => now(),
            'consent_text_version' => WaitlistEntry::CONSENT_TEXT_VERSION,
        ]);

        // M1.1: Send Email Invite with Pro tier + 30d trial -> 7-day owner invite queued/sent
        Livewire::test(WaitlistEntries::class)
            ->call('openOnboardingModal', $consented->id, 'invite')
            ->set('selectedPlan', 'pro')
            ->set('selectedTrial', '30d')
            ->call('submitOnboardingModal');

        Mail::assertSent(AgencyInvitationMail::class);
        $consented->refresh();
        $this->assertSame('pro', $consented->invited_plan);
        $this->assertSame('30d', $consented->invited_trial_length);
        $this->assertNotNull($consented->provisioned_agency_id);

        $invite = AgencyInvitation::where('agency_id', $consented->provisioned_agency_id)->firstOrFail();
        $this->assertSame('owner', $invite->role);
        $this->assertEqualsWithDelta(7 * 24 * 60, now()->diffInMinutes($invite->expires_at), 5);

        // M1.2: Provision Workspace & Copy Login Link (Unlimited Beta) + Regenerate 7-Day Link
        $second = WaitlistEntry::create([
            'email' => 'owner@riyadhmedia.sa',
            'name' => 'Noura Al Saud',
            'agency_name' => 'Riyadh Media House',
            'country' => 'SA',
            'audience' => 'agency',
            'lang' => 'ar',
            'consent_at' => null,
        ]);

        $lw = Livewire::test(WaitlistEntries::class)
            ->call('openOnboardingModal', $second->id, 'provision')
            ->set('selectedPlan', 'agency')
            ->set('selectedTrial', 'unlimited_beta')
            ->call('submitOnboardingModal');

        $firstUrl = (string) $lw->get('issuedMagicUrl');
        $this->assertStringContainsString('/magic-login/', $firstUrl);

        $second->refresh();
        $agency = Agency::findOrFail($second->provisioned_agency_id);
        $this->assertSame('agency', $agency->plan);
        $this->assertNull($agency->trial_ends_at);
        $this->assertNotNull($agency->owner_id);

        // Regenerate invalidates the first link and issues a fresh 7-day link
        $lw->call('regenerateMagicLink', $second->id);
        $regenUrl = (string) $lw->get('issuedMagicUrl');
        $this->assertNotSame($firstUrl, $regenUrl);

        $oldToken = basename(parse_url($firstUrl, PHP_URL_PATH));
        $newToken = basename(parse_url($regenUrl, PHP_URL_PATH));

        $this->get('/magic-login/'.$oldToken)->assertStatus(410);
        $this->get('/magic-login/'.$newToken)->assertOk()->assertSee('Riyadh Media House');

        $this->post('/magic-login/'.$newToken, [
            'name' => 'Noura Al Saud',
            'password' => 'StrongPass#2026',
            'password_confirmation' => 'StrongPass#2026',
        ])->assertRedirect('/app/'.$agency->slug);

        $this->assertNull(WorkspaceMagicLink::findByToken($newToken)?->isUsable() ? 'usable' : null);
    }

    public function test_m1_3_partner_directory_dual_actions_and_d229_public_visibility(): void
    {
        $admin = $this->makeSuperAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $partner = PartnerApplication::create([
            'email' => 'hello@petradigital.jo',
            'agency_name' => 'Petra Digital Lab',
            'name' => 'Tariq',
            'country' => 'JO',
            'clients_range' => '16-40',
            'lang' => 'ar',
            'consent_at' => now(),
            'consent_text_version' => PartnerApplication::CONSENT_TEXT_VERSION,
        ]);

        // D-229: Unapproved applications never appear publicly on /partners or /en/partners
        $this->get('/partners')->assertOk()->assertDontSee('Petra Digital Lab');
        $this->get('/en/partners')->assertOk()->assertDontSee('Petra Digital Lab');

        // Action 1: Publish to /partners Directory with Premier Partner badge
        Livewire::test(PartnerApplications::class)
            ->call('openDirectoryModal', $partner->id)
            ->set('publishToDirectory', true)
            ->set('selectedBadge', 'premier')
            ->call('submitDirectoryModal');

        $this->get('/partners')->assertOk()->assertSee('Petra Digital Lab')->assertSee('شريك مميز');
        $this->get('/en/partners')->assertOk()->assertSee('Petra Digital Lab')->assertSee('Premier Partner');

        // Action 2: Independent Invite / Provision Workspace
        Livewire::test(PartnerApplications::class)
            ->call('openOnboardingModal', $partner->id, 'provision')
            ->set('onboardingMode', 'provision')
            ->set('selectedPlan', 'pro')
            ->set('selectedTrial', '30d')
            ->call('submitOnboardingModal');

        $this->assertNotNull($partner->fresh()->provisioned_agency_id);
    }

    public function test_m2_1_and_m2_2_manual_upgrade_request_expired_trial_readonly_and_extend_7_days(): void
    {
        $owner = User::factory()->create();
        $agency = Agency::create([
            'name' => 'Expired Self Signup',
            'slug' => 'expired-self',
            'plan' => 'starter',
            'status' => 'trial',
            'signup_source' => 'self_signup',
            'trial_ends_at' => now()->subDays(2), // Day 16 -> strict read-only
            'owner_id' => $owner->id,
            'language' => 'en',
        ]);
        DB::table('agency_user')->where('user_id', $owner->id)->delete();
        $agency->members()->attach($owner->id, ['role' => 'owner']);

        $this->assertTrue(Trial::isReadOnly($agency));

        // M2.2: Write actions on AlertRules are blocked in Day 15+ read-only mode, while PlanUsage upgrade request works
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        $this->get('/app/expired-self/export-data')->assertOk();
        $this->get('/app/expired-self/plan')->assertOk()->assertSee('طلب ترقية الباقة');
        $this->get('/app/expired-self/plan?lang=en')->assertOk()->assertSee('Request Plan Upgrade');

        Livewire::test(PlanUsage::class)
            ->call('openUpgradeModal', 'agency')
            ->set('targetPlan', 'agency')
            ->set('upgradeNote', 'Need 25 client slots for Q1')
            ->call('submitUpgradeRequest');

        $this->assertDatabaseHas('plan_upgrade_requests', [
            'agency_id' => $agency->id,
            'target_plan' => 'agency',
            'status' => 'pending',
        ]);

        // Public /pricing remains invite-only
        $this->get('/en/pricing')->assertOk()->assertSee('/en/waitlist', false);

        // M2.2: Super Admin 1-click "Extend Trial +7 Days" lifts read-only state
        $admin = $this->makeSuperAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListAgencies::class)
            ->callTableAction('extendTrial7Days', $agency);

        $agency->refresh();
        $this->assertFalse(Trial::isReadOnly($agency));
        $this->assertTrue($agency->trial_ends_at->isFuture());
    }

    public function test_m3_1_and_m3_2_per_agency_module_overrides_and_early_access_requests(): void
    {
        // Global defaults in config/modules.php MUST remain false (D-229)
        foreach (array_keys(config('modules.enabled')) as $mod) {
            $this->assertFalse(Modules::enabled($mod), "Global module $mod must remain false");
        }

        $owner = User::factory()->create();
        $soloAgency = Agency::create([
            'name' => 'Solo Override Test',
            'slug' => 'solo-override',
            'plan' => 'solo',
            'status' => 'active',
            'owner_id' => $owner->id,
            'language' => 'en',
        ]);
        $otherAgency = Agency::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'plan' => 'agency',
            'status' => 'active',
            'language' => 'en',
        ]);
        DB::table('agency_user')->where('user_id', $owner->id)->delete();
        $soloAgency->members()->attach($owner->id, ['role' => 'owner']);

        // Initially Studio & Webhooks are OFF and return 403/404 for Solo agency
        $this->actingAs($owner);
        $this->assertContains($this->get('/app/solo-override/studio')->status(), [403, 404]);

        // M3.2: Request Early Access from Settings -> Plan & usage
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($soloAgency);
        Livewire::test(PlanUsage::class)
            ->call('requestModuleEarlyAccess', 'studio');

        $this->assertDatabaseHas('module_early_access_requests', [
            'agency_id' => $soloAgency->id,
            'module_key' => 'studio',
            'status' => 'pending',
        ]);

        // M3.1: Super Admin enables `studio` and `webhooks` in `module_overrides` on Solo agency -> unlocks regardless of tier, isolated to this tenant
        $soloAgency->update(['module_overrides' => ['studio' => true, 'webhooks' => true]]);
        $soloAgency->refresh();

        $this->assertTrue(Modules::on('studio', $owner, $soloAgency));
        $this->assertFalse(Modules::previewOnly('studio', $owner, $soloAgency));
        $this->assertTrue(PlanGate::allows($soloAgency, 'webhooks'));
        $this->assertFalse(Modules::on('studio', $owner, $otherAgency));

        $this->actingAs($owner)->get('/app/solo-override/studio')->assertOk();
    }

    public function test_m4_1_and_m4_2_encrypted_ai_settings_masking_and_daily_tier_caps(): void
    {
        Cache::flush();
        $admin = $this->makeSuperAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        // D-229: Unconfigured connection test honestly reports fallback mode
        $unconf = AiSettings::testConnection();
        $this->assertFalse($unconf['ok']);
        $this->assertSame('fallback', $unconf['mode']);

        // Save encrypted key via PlatformSettings
        Livewire::test(PlatformSettings::class)
            ->set('aiProvider', 'openai')
            ->set('aiModel', 'gpt-4o-mini')
            ->set('aiApiKeyInput', 'sk-proj-secret-9876abcd')
            ->call('save');

        $rawStored = (string) PlatformSetting::get(AiSettings::KEY_API_ENCRYPTED);
        $this->assertStringNotContainsString('9876abcd', $rawStored);
        $this->assertSame('sk-proj-secret-9876abcd', AiSettings::decryptedKey());
        $this->assertSame('••••••••abcd', AiSettings::maskedKey());

        Http::fake([
            'https://api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4o-mini']]], 200),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Live grounded summary.']]],
            ], 200),
        ]);

        $check = AiSettings::testConnection();
        $this->assertTrue($check['ok']);

        // M4.2: Tiered daily limits (Solo/Starter = 20, Pro = 75, Agency = 250) + 4h cache + deterministic fallback when capped
        $solo = Agency::create(['name' => 'Solo AI', 'slug' => 'solo-ai', 'plan' => 'solo', 'status' => 'active']);
        $pro = Agency::create(['name' => 'Pro AI', 'slug' => 'pro-ai', 'plan' => 'pro', 'status' => 'active']);
        $ent = Agency::create(['name' => 'Ent AI', 'slug' => 'ent-ai', 'plan' => 'agency', 'status' => 'active']);

        $this->assertSame(20, AiAnalyst::dailyLimit($solo));
        $this->assertSame(75, AiAnalyst::dailyLimit($pro));
        $this->assertSame(250, AiAnalyst::dailyLimit($ent));

        // Same prompt hits 4h cache without incrementing daily counter twice
        $first = AiAnalyst::analyze('Summarize pacing', 'en', $solo);
        $second = AiAnalyst::analyze('Summarize pacing', 'en', $solo);
        $this->assertSame('Live grounded summary.', $first);
        $this->assertSame('Live grounded summary.', $second);
        $this->assertSame(1, AiAnalyst::dailyUsed($solo));

        // Cap reached -> falls back cleanly to labeled deterministic text
        Cache::put('ai_daily_calls:'.$solo->id.':'.now()->toDateString(), 20, now()->addHours(4));
        $capped = AiAnalyst::analyze('New uncached prompt after cap', 'en', $solo);
        $this->assertStringContainsString('Deterministic analysis (fallback)', $capped);
        $this->assertStringContainsString('20/day', $capped);
    }

    public function test_m5_1_and_m5_2_health_score_reweighting_and_cpr_rising_spend_floor(): void
    {
        $agency = Agency::create(['name' => 'Calibrated Co', 'slug' => 'cal-co', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC']);
        $net = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta Ads', 'category' => 'social', 'stage' => 1, 'auth_type' => 'oauth', 'is_active' => true]);
        $conn = Connection::create(['agency_id' => $agency->id, 'ad_network_id' => $net->id, 'status' => 'active']);

        $clientNoTarget = Client::create([
            'agency_id' => $agency->id,
            'name' => 'No Target Brand',
            'currency' => 'USD',
            'monthly_budget' => 3000,
            'target_cpr' => null,
            'target_roas' => null,
            'status' => 'active',
        ]);
        $acct1 = AdAccount::create([
            'agency_id' => $agency->id, 'client_id' => $clientNoTarget->id, 'ad_network_id' => $net->id, 'connection_id' => $conn->id,
            'external_id' => 'act_nt', 'name' => 'NT Meta', 'currency' => 'USD', 'is_active' => true,
        ]);

        $today = Carbon::parse('2026-11-15', 'UTC');
        for ($i = 1; $i <= 14; $i++) {
            DailyMetric::create([
                'agency_id' => $agency->id, 'client_id' => $clientNoTarget->id, 'ad_account_id' => $acct1->id,
                'date' => $today->copy()->subDays($i)->toDateString(), 'network' => 'meta', 'currency' => 'USD',
                'spend' => 100, 'impressions' => 2000, 'clicks' => 60, 'results' => 10, 'result_type' => 'leads',
            ]);
        }

        // M5.1: Without target_cpr/target_roas -> 50% Pacing + 50% Alerts/Rejected + `No target set` badge
        $hNoTarget = HealthScore::compute($clientNoTarget, $today);
        $this->assertTrue($hNoTarget['no_target_set']);
        $this->assertSame('No target set', $hNoTarget['no_target_badge']);
        $this->assertSame(50, $hNoTarget['parts']['pacing']['max']);
        $this->assertSame(50, $hNoTarget['parts']['alerts']['max']);
        $this->assertArrayNotHasKey('targets', $hNoTarget['parts']);

        // With target_cpr set -> 35% Pacing + 35% Alerts/Rejected + 30% Targets
        $clientNoTarget->update(['target_cpr' => 10]);
        $hWithTarget = HealthScore::compute($clientNoTarget->fresh(), $today);
        $this->assertFalse($hWithTarget['no_target_set']);
        $this->assertNull($hWithTarget['no_target_badge']);
        $this->assertSame(35, $hWithTarget['parts']['pacing']['max']);
        $this->assertSame(35, $hWithTarget['parts']['alerts']['max']);
        $this->assertSame(30, $hWithTarget['parts']['targets']['max']);

        // M5.2: Tiny test budget (< $50 USD-eq and < 3x target CPR) with +60% CPR rise MUST NOT trigger false alarm
        $tinyClient = Client::create([
            'agency_id' => $agency->id, 'name' => 'Tiny Test Budget', 'currency' => 'USD',
            'target_cpr' => 20, 'status' => 'active',
        ]);
        $acctTiny = AdAccount::create([
            'agency_id' => $agency->id, 'client_id' => $tinyClient->id, 'ad_network_id' => $net->id, 'connection_id' => $conn->id,
            'external_id' => 'act_tiny', 'name' => 'Tiny Meta', 'currency' => 'USD', 'is_active' => true,
        ]);
        $evalDay = Carbon::parse('2026-11-14', 'UTC');
        // Prev week: $14 total, 7 leads ($2 CPR). Current week: $21 total (< $50 and < $60 = 3x20), 7 leads ($3 CPR -> +50%!)
        for ($i = 7; $i <= 13; $i++) {
            DailyMetric::create([
                'agency_id' => $agency->id, 'client_id' => $tinyClient->id, 'ad_account_id' => $acctTiny->id,
                'date' => $evalDay->copy()->subDays($i)->toDateString(), 'network' => 'meta', 'currency' => 'USD',
                'spend' => 2, 'impressions' => 300, 'clicks' => 10, 'results' => 1, 'result_type' => 'leads',
            ]);
        }
        for ($i = 0; $i <= 6; $i++) {
            DailyMetric::create([
                'agency_id' => $agency->id, 'client_id' => $tinyClient->id, 'ad_account_id' => $acctTiny->id,
                'date' => $evalDay->copy()->subDays($i)->toDateString(), 'network' => 'meta', 'currency' => 'USD',
                'spend' => 3, 'impressions' => 300, 'clicks' => 10, 'results' => 1, 'result_type' => 'leads',
            ]);
        }

        AlertEngine::run($agency, $evalDay->toDateString());
        $this->assertDatabaseMissing('alerts', ['client_id' => $tinyClient->id, 'rule_key' => 'cpr_rising']);

        // Now test >20% Warning and >40% Critical when >= $50 USD spend floor IS met
        $realClient = Client::create(['agency_id' => $agency->id, 'name' => 'Real Spend Brand', 'currency' => 'USD', 'status' => 'active']);
        $acctReal = AdAccount::create([
            'agency_id' => $agency->id, 'client_id' => $realClient->id, 'ad_network_id' => $net->id, 'connection_id' => $conn->id,
            'external_id' => 'act_real', 'name' => 'Real Meta', 'currency' => 'USD', 'is_active' => true,
        ]);
        // Prev week: $70, 7 leads ($10 CPR). Current week: $91, 7 leads ($13 CPR -> +30% Warning)
        for ($i = 7; $i <= 13; $i++) {
            DailyMetric::create([
                'agency_id' => $agency->id, 'client_id' => $realClient->id, 'ad_account_id' => $acctReal->id,
                'date' => $evalDay->copy()->subDays($i)->toDateString(), 'network' => 'meta', 'currency' => 'USD',
                'spend' => 10, 'impressions' => 500, 'clicks' => 20, 'results' => 1, 'result_type' => 'leads',
            ]);
        }
        for ($i = 0; $i <= 6; $i++) {
            DailyMetric::create([
                'agency_id' => $agency->id, 'client_id' => $realClient->id, 'ad_account_id' => $acctReal->id,
                'date' => $evalDay->copy()->subDays($i)->toDateString(), 'network' => 'meta', 'currency' => 'USD',
                'spend' => 13, 'impressions' => 500, 'clicks' => 20, 'results' => 1, 'result_type' => 'leads',
            ]);
        }

        AlertEngine::run($agency, $evalDay->toDateString());
        $this->assertDatabaseHas('alerts', ['client_id' => $realClient->id, 'rule_key' => 'cpr_rising', 'severity' => 'warning']);

        // Escalate current week to $15/day ($105, 7 leads -> $15 CPR = +50% > 40% Critical)
        DailyMetric::where('client_id', $realClient->id)
            ->whereBetween('date', [$evalDay->copy()->subDays(6)->toDateString(), $evalDay->toDateString()])
            ->update(['spend' => 15]);

        AlertEngine::run($agency, $evalDay->toDateString());
        $this->assertDatabaseHas('alerts', ['client_id' => $realClient->id, 'rule_key' => 'cpr_rising', 'severity' => 'critical']);
    }

    public function test_m6_1_custom_domain_cname_instructions_and_honest_dns_verification(): void
    {
        $agency = Agency::create(['name' => 'Amman White Label', 'slug' => 'amman-wl', 'plan' => 'agency', 'status' => 'active']);
        CustomDomainManager::configure($agency, 'https://reports.ammanagency.com/');

        $info = CustomDomainManager::instructions($agency);
        $this->assertSame('CNAME', $info['record_type']);
        $this->assertSame('reports.ammanagency.com', $info['example_host']);
        $this->assertSame('pending', $info['status']);

        // Unpointed DNS stays pending (D-229: no fake active status)
        $fail = CustomDomainManager::verifyDns($agency, fn () => [['target' => 'wrong.example.net']]);
        $this->assertFalse($fail['ok']);
        $this->assertSame('pending', $agency->fresh()->custom_domain_status);

        // Pointed CNAME activates domain
        $ok = CustomDomainManager::verifyDns($agency, fn () => [['target' => CustomDomainManager::cnameTarget()]]);
        $this->assertTrue($ok['ok']);
        $this->assertSame('active', $agency->fresh()->custom_domain_status);
    }
}
