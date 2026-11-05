<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\WaitlistEntries;
use App\Filament\Pages\StudioPage;
use App\Models\Agency;
use App\Models\BrandKit;
use App\Models\Client;
use App\Models\CreativeBrief;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\StudioCopyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Wave 4 Studio Slice 1 (#134, #135, #131, #136, #54) + Super Admin Waitlist viewer. */
class Wave4StudioSlice1Test extends TestCase
{
    use RefreshDatabase;

    public function test_dialect_copy_generator_and_brand_kit_banned_word_detection(): void
    {
        $agency = Agency::create(['name' => 'Studio Co', 'slug' => 'studio-co', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'أطياب الخليج', 'currency' => 'SAR', 'status' => 'active']);

        $gulf = StudioCopyGenerator::generate($client, 'gulf', 'purchase', 'خصم 30% بمناسبة رمضان');
        $this->assertStringContainsString('تبغى', $gulf['hook']);
        $this->assertSame('اطلب الحين', $gulf['cta']);

        $lev = StudioCopyGenerator::generate($client, 'levantine', 'lead', 'استشارة مجانية');
        $this->assertStringContainsString('بدك', $lev['hook']);

        $kit = BrandKit::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'banned_words' => ['مجاناً', 'cheapest'],
        ]);
        $this->assertSame(['مجاناً'], $kit->violations('احصل على العرض مجاناً اليوم'));
    }

    public function test_studio_page_saves_brand_kit_asset_and_brief_with_client_approval_flow(): void
    {
        $agency = Agency::create(['name' => 'Creative Agency', 'slug' => 'creative-agency', 'plan' => 'pro', 'status' => 'active', 'currency' => 'USD']);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Noor Clinic', 'currency' => 'JOD', 'status' => 'active']);

        $this->actingAs($owner);
        \Filament\Facades\Filament::setTenant($agency);

        Livewire::test(StudioPage::class)
            ->set('clientId', $client->id)
            ->set('primaryColor', '#14B8A6')
            ->set('bannedWordsInput', 'guaranteed, free')
            ->call('saveBrandKit')
            ->set('assetTitle', 'Ramadan Hero Reel')
            ->set('assetFormat', 'video')
            ->set('assetRatio', '9:16')
            ->set('assetTags', 'ramadan, reel')
            ->call('addAsset')
            ->set('dialect', 'levantine')
            ->set('offer', '20% off laser package')
            ->call('generateDraft')
            ->call('saveBrief');

        $this->assertDatabaseHas('brand_kits', ['client_id' => $client->id, 'primary_color' => '#14B8A6']);
        $this->assertDatabaseHas('creative_assets', ['client_id' => $client->id, 'title' => 'Ramadan Hero Reel', 'aspect_ratio' => '9:16']);

        $brief = CreativeBrief::where('client_id', $client->id)->firstOrFail();
        $this->assertSame('pending_approval', $brief->status);

        // Client opens signed approval link without login and approves it (#54)
        auth()->logout();
        $this->get('/studio/brief/'.$brief->share_token)->assertOk()->assertSee('Noor Clinic');
        $this->post('/studio/brief/'.$brief->share_token, [
            'decision' => 'approved',
            'client_feedback' => 'ممتاز، اعتمدوا الإعلان',
        ])->assertRedirect('/studio/brief/'.$brief->share_token);

        $brief->refresh();
        $this->assertSame('approved', $brief->status);
        $this->assertSame('ممتاز، اعتمدوا الإعلان', $brief->client_feedback);
        $this->assertNotNull($brief->approved_at);
    }

    public function test_super_admin_waitlist_page_lists_entries(): void
    {
        WaitlistEntry::create([
            'name' => 'Rami',
            'agency_name' => 'Amman Digital',
            'email' => 'rami@ammandigital.jo',
            'monthly_spend' => '50k_200k',
            'lang' => 'ar',
        ]);
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        $this->actingAs($admin);
        $this->assertTrue(WaitlistEntries::canAccess());

        Livewire::test(WaitlistEntries::class)
            ->assertOk()
            ->assertSee('Amman Digital')
            ->assertSee('rami@ammandigital.jo');
    }
}
