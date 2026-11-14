<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientCalendarPage;
use App\Filament\Pages\DailyDesk;
use App\Filament\Pages\LaunchCheckPage;
use App\Filament\Pages\StudioPage;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\CreativeBrief;
use App\Models\DailyMetric;
use App\Models\DeliveryWeek;
use App\Models\User;
use App\Support\ClientCalendar;
use App\Support\DeliveryOutcomes;
use App\Support\LaunchPreflight;
use App\Support\MenaCalendar;
use App\Support\ReadOnlyGuard;
use App\Support\SectorPacks;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 1a (E2-077, D-308 / DS-20..23): light JSON sector-pack presets, Charity donation labels & 0-impressions guard,
 * per-ad neutral permit reminders, Real Estate 5-form Arabic countdown + calendar entry, and DeliveryWeek manual + AR/EN CSV.
 */
class SectorPacksTest extends TestCase
{
    use RefreshDatabase;

    private function asTenant(Agency $agency, User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
    }

    private function makeWorkspace(string $slug = 'acme-sp', string $lang = 'en'): array
    {
        $agency = Agency::create([
            'name' => 'Al Manar Agency',
            'slug' => $slug,
            'plan' => 'pro',
            'status' => 'active',
            'timezone' => 'Asia/Amman',
            'country' => 'JO',
            'language' => $lang,
            'report_powered_by' => false,
        ]);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);

        return [$agency, $owner];
    }

    public function test_json_sector_packs_load_for_every_configured_vertical(): void
    {
        SectorPacks::flushCache();
        $all = SectorPacks::all();
        $verticals = array_keys(config('adsranger.verticals'));

        $this->assertNotEmpty($verticals);
        foreach ($verticals as $vertical) {
            $this->assertArrayHasKey($vertical, $all, "Missing JSON sector pack for vertical [{$vertical}]");
            $this->assertSame($vertical, $all[$vertical]['key']);
            $this->assertSame('sector_packs', $all[$vertical]['kind']);
        }

        $fallback = SectorPacks::for('unknown_custom_vertical');
        $this->assertSame('other', $fallback['key']);
    }

    public function test_charity_pack_relabels_donors_and_donation_value_guards_zero_impressions_and_highlights_seasons(): void
    {
        [$agency, $owner] = $this->makeWorkspace('charity-sp', 'en');
        $charity = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Noor Relief Foundation',
            'vertical' => 'charity',
            'currency' => 'JOD',
            'market' => 'JO',
            'status' => 'active',
        ]);

        // 0-impressions guard: returns null in support layer and renders '–' without division by zero.
        $this->assertNull(SectorPacks::donationPerThousandImpressions(['impressions' => 0, 'revenue' => 250]));
        $zeroExtras = SectorPacks::extraMetrics($charity, ['impressions' => 0, 'revenue' => 0]);
        $this->assertCount(1, $zeroExtras);
        $this->assertSame('donation_per_1k_impressions', $zeroExtras[0]['key']);
        $this->assertNull($zeroExtras[0]['value']);
        $this->assertSame('–', $zeroExtras[0]['formatted']);

        $this->asTenant($agency, $owner);
        Livewire::test(ViewClient::class, ['record' => $charity->id])
            ->assertSee('Donors')
            ->assertSee('Donation value')
            ->assertSee('Donation per 1,000 impressions')
            ->assertSee('Ramadan')
            ->assertSee('Last ten nights of Ramadan')
            ->assertSee('First 10 days of Dhul Hijjah');

        // Verify Last Ten Nights is derived as Ramadan start + 20 days via MenaCalendar.
        $seasons = SectorPacks::highlightedSeasonsForClient($charity, '2026-11-14');
        $byKey = collect($seasons)->keyBy('key');
        $this->assertTrue($byKey->has('ramadan'));
        $this->assertTrue($byKey->has('last_ten_nights'));
        $this->assertTrue($byKey->has('dhul_hijjah'));

        $ramadanEvents = collect(MenaCalendar::events('2026-11-14', '2027-11-14', 'JO'))->where('key', 'ramadan')->values();
        $expectedLastTenStart = Carbon::parse($ramadanEvents[0]['start'])->addDays(20)->toDateString();
        $this->assertSame($expectedLastTenStart, $byKey['last_ten_nights']['start']);

        // Populate JOD donation metrics: 25 JOD / 2,000 impressions * 1,000 = 12.500 JOD (3-decimal JOD via ResultResolver::money).
        DailyMetric::create([
            'agency_id' => $agency->id,
            'client_id' => $charity->id,
            'date' => now('Asia/Amman')->subDays(2)->toDateString(),
            'network' => 'meta',
            'currency' => 'JOD',
            'spend' => 10,
            'impressions' => 2000,
            'clicks' => 80,
            'link_clicks' => 60,
            'purchases' => 5,
            'results' => 5,
            'revenue' => 25,
            'result_type' => 'purchase',
            'roas_eligible' => true,
        ]);

        Livewire::test(ViewClient::class, ['record' => $charity->id])
            ->assertSee('12.500 JOD')
            ->assertSee('25.000 JOD');

        // Arabic rendering: «المتبرعون», «قيمة التبرعات», «التبرعات لكل 1,000 ظهور», and Arabic season names.
        $agency->update(['language' => 'ar']);
        app()->setLocale('ar');
        Livewire::test(ViewClient::class, ['record' => $charity->id])
            ->assertSee('المتبرعون')
            ->assertSee('قيمة التبرعات')
            ->assertSee('التبرعات لكل 1,000 ظهور')
            ->assertSee('رمضان')
            ->assertSee('العشر الأواخر من رمضان')
            ->assertSee('العشر الأوائل من ذي الحجة')
            ->assertSee('12.500 JOD');
        $agency->update(['language' => 'en']);
        app()->setLocale('en');

        // Client calendar highlights Ramadan, Last Ten Nights, and Dhul Hijjah for the Charity client.
        $calEvents = ClientCalendar::events($agency, $owner, '2026-11-14', '2027-11-14', $charity->id);
        $highlightedTitles = array_column(array_filter($calEvents, fn (array $e) => ! empty($e['highlighted'])), 'title');
        $joinedTitles = implode(' | ', $highlightedTitles);
        $this->assertStringContainsString('Ramadan', $joinedTitles);
        $this->assertStringContainsString('Last ten nights of Ramadan', $joinedTitles);
        $this->assertStringContainsString('First 10 days of Dhul Hijjah', $joinedTitles);

        // Public client report (/r/{token}): shows Donors / المتبرعون and zero AdsRanger branding when PoweredBy is off.
        $link = ClientLink::issue($charity, $owner, 'Board link');
        $resEn = $this->get('/r/'.$link->token.'?lang=en')->assertOk()->assertSee('Donors');
        $this->assertStringNotContainsStringIgnoringCase('AdsRanger', $resEn->getContent());

        $resAr = $this->get('/r/'.$link->token.'?lang=ar')->assertOk()->assertSee('المتبرعون');
        $this->assertStringNotContainsStringIgnoringCase('AdsRanger', $resAr->getContent());
    }

    public function test_per_ad_licence_permits_surface_neutral_reminders_on_briefs_and_launch_preflight(): void
    {
        config(['modules.enabled.studio' => true]);
        [$agency, $owner] = $this->makeWorkspace('permits-sp', 'en');
        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Dar Al Emaar',
            'vertical' => 'real_estate',
            'currency' => 'SAR',
            'market' => 'SA',
            'status' => 'active',
        ]);

        $this->asTenant($agency, $owner);

        // Save two per-ad permits via ViewClient; preserve 30-day expiry reminder on DailyDesk.
        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->callAction('addLicence', [
                'kind' => 'real_estate',
                'number' => 'FAL-99001',
                'ad_name' => 'Al Narjis Villas',
                'expires_on' => now()->addDays(18)->toDateString(),
                'note' => 'North Riyadh phase 1',
            ])
            ->assertHasNoActionErrors()
            ->callAction('addLicence', [
                'kind' => 'real_estate',
                'number' => 'FAL-99002',
                'ad_name' => 'Corniche Tower',
                'expires_on' => now()->addDays(60)->toDateString(),
            ])
            ->assertHasNoActionErrors()
            ->assertSee('FAL-99001')
            ->assertSee('Al Narjis Villas');

        Livewire::test(DailyDesk::class)->assertSee('Licences to renew')->assertSee('FAL-99001');

        // StudioPage & public creative brief surface only matching permits and never say compliant / not compliant.
        Livewire::test(StudioPage::class)
            ->set('clientId', $client->id)
            ->set('briefTitle', 'Al Narjis Villas — Spring Offer')
            ->set('headline', 'Own in Al Narjis Villas')
            ->set('primaryText', 'Book your viewing this week.')
            ->assertSee('FAL-99001')
            ->assertDontSee('FAL-99002')
            ->call('saveBrief');

        $brief = CreativeBrief::where('client_id', $client->id)->latest('id')->firstOrFail();
        $briefHtml = $this->get('/studio/brief/'.$brief->share_token)
            ->assertOk()
            ->assertSee('FAL-99001')
            ->assertDontSee('FAL-99002')
            ->getContent();
        $this->assertStringNotContainsStringIgnoringCase('AdsRanger', $briefHtml);
        $this->assertStringNotContainsStringIgnoringCase('not compliant', $briefHtml);
        $this->assertStringNotContainsStringIgnoringCase('compliant', $briefHtml);

        // LaunchPreflight + LaunchCheckPage: neutral reminder only, never "compliant" or "not compliant".
        Http::fake(['https://emaar.example/*' => Http::response('<html><body><script>fbq("init","1");</script></body></html>', 200)]);
        $url = 'https://emaar.example/narjis?utm_source=meta&utm_medium=paid&utm_campaign=spring';

        $missingCheck = collect(LaunchPreflight::run($client, $url, 'Book a private tour today', true, 'Al Narjis Villas'))->keyBy('key')['licence'];
        $this->assertFalse($missingCheck['ok']);
        $this->assertStringContainsString('FAL-99001', $missingCheck['message']);
        $this->assertStringNotContainsString('FAL-99002', $missingCheck['message']);
        $this->assertStringNotContainsStringIgnoringCase('compliant', $missingCheck['message']);
        $this->assertStringNotContainsString('متوافق', $missingCheck['message']);

        $presentCheck = collect(LaunchPreflight::run($client, $url, 'Book today — Permit FAL-99001', true, 'Al Narjis Villas'))->keyBy('key')['licence'];
        $this->assertTrue($presentCheck['ok']);
        $this->assertStringContainsString('FAL-99001', $presentCheck['message']);
        $this->assertStringNotContainsStringIgnoringCase('compliant', $presentCheck['message']);

        $pageHtml = Livewire::test(LaunchCheckPage::class)
            ->set('clientId', $client->id)
            ->set('url', $url)
            ->set('adName', 'Al Narjis Villas')
            ->set('adText', 'Book a private tour today')
            ->call('check')
            ->assertSee('FAL-99001')
            ->html();
        $this->assertStringNotContainsStringIgnoringCase('not compliant', $pageHtml);
    }

    public function test_real_estate_off_plan_launch_countdown_supports_all_five_arabic_plural_forms_and_client_calendar(): void
    {
        [$agency, $owner] = $this->makeWorkspace('realestate-sp', 'ar');
        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'واحة العقار',
            'vertical' => 'real_estate',
            'currency' => 'SAR',
            'market' => 'SA',
            'status' => 'active',
        ]);

        $launchDate = Carbon::parse('2026-11-29');
        $this->asTenant($agency, $owner);

        Livewire::test(EditClient::class, ['record' => $client->id])
            ->fillForm([
                'vertical' => 'real_estate',
                'launch_project_name' => 'برج الياسمين',
                'launch_date' => $launchDate->toDateString(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();
        $this->assertSame('برج الياسمين', $client->launch_project_name);
        $this->assertSame('2026-11-29', $client->launch_date?->toDateString());

        // Verify all 5 Arabic plural forms ({0}, {1}, {2}, [3,10], [11,*]) + Western digits via TextClean::western.
        $cases = [
            '2026-11-29' => 'الإطلاق اليوم',
            '2026-11-28' => 'الإطلاق خلال يوم واحد',
            '2026-11-27' => 'الإطلاق خلال يومين',
            '2026-11-24' => 'الإطلاق خلال 5 أيام',
            '2026-11-14' => 'الإطلاق خلال 15 يومًا',
        ];

        foreach ($cases as $today => $expectedAr) {
            $cd = SectorPacks::launchCountdown($client, $today, 'ar');
            $this->assertNotNull($cd);
            $this->assertSame($expectedAr, $cd['text']);
            $this->assertSame(0, preg_match('/[٠-٩۰-۹]/u', $cd['text']), 'Countdown digits must be Western');
        }

        // English countdown check.
        $this->assertSame('Launch in 15 days', SectorPacks::launchCountdown($client, '2026-11-14', 'en')['text']);

        // ViewClient renders the countdown banner and ClientCalendar includes the launch entry.
        app()->setLocale('ar');
        Carbon::setTestNow('2026-11-14 10:00:00');
        try {
            Livewire::test(ViewClient::class, ['record' => $client->id])
                ->assertSee('برج الياسمين')
                ->assertSee('الإطلاق خلال 15 يومًا');

            $events = ClientCalendar::events($agency, $owner, '2026-11-14', '2026-12-14', $client->id);
            $launches = array_values(array_filter($events, fn (array $e) => $e['kind'] === 'launch'));
            $this->assertCount(1, $launches);
            $this->assertSame('2026-11-29', $launches[0]['date']);
            $this->assertSame('برج الياسمين', $launches[0]['title']);

            Livewire::test(ClientCalendarPage::class)
                ->set('days', 30)
                ->assertSee('برج الياسمين');
        } finally {
            Carbon::setTestNow();
            app()->setLocale('en');
        }
    }

    public function test_delivery_weeks_support_manual_entry_ar_en_eastern_digit_csv_import_hide_spend_and_tenant_role_guards(): void
    {
        config(['modules.enabled.view_as' => true]);
        [$agency, $owner] = $this->makeWorkspace('delivery-sp', 'en');
        $restaurant = Client::create([
            'agency_id' => $agency->id,
            'name' => 'beit al shawarma',
            'vertical' => 'restaurant',
            'currency' => 'JOD',
            'market' => 'JO',
            'status' => 'active',
            'hide_spend' => false,
        ]);

        $week1 = now('Asia/Amman')->subWeeks(2)->startOfWeek(CarbonInterface::SUNDAY);
        $week2 = now('Asia/Amman')->subWeeks(1)->startOfWeek(CarbonInterface::SUNDAY);

        // 7 days of spend across week1..week2 = 70.000 JOD total spend.
        foreach (range(0, 6) as $i) {
            DailyMetric::create([
                'agency_id' => $agency->id,
                'client_id' => $restaurant->id,
                'date' => $week1->copy()->addDays($i)->toDateString(),
                'network' => 'meta',
                'currency' => 'JOD',
                'spend' => 10,
                'impressions' => 1000,
                'clicks' => 50,
                'results' => 10,
            ]);
        }

        // Arabic CSV with Eastern-Arabic digits + English CSV import.
        $easternWeek1 = strtr($week1->toDateString(), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        $arCsv = "بداية الأسبوع,التطبيق,عدد الطلبات,قيمة الطلبات\n{$easternWeek1},طلبات,١٠٠,١٢٠٠.٧٥٠\nتاريخ خاطئ,طلبات,١٠,٥٠\n";
        $resAr = DeliveryOutcomes::importCsv($restaurant, $arCsv, $owner);
        $this->assertSame(1, $resAr['saved']);
        $this->assertSame(1, $resAr['skipped']);
        $this->assertFalse($resAr['missing_columns']);

        $enCsv = "Week start,App,Orders,Order value\n{$week2->toDateString()},Careem Food,20,300.250\n";
        $resEn = DeliveryOutcomes::importCsv($restaurant, $enCsv, $owner);
        $this->assertSame(1, $resEn['saved']);

        // Manual entry via ViewClient action with Eastern-Arabic digits (20 more orders on Jahez -> total 140 orders, 70 / 140 = 0.500 JOD/order).
        $this->asTenant($agency, $owner);
        Livewire::test(ViewClient::class, ['record' => $restaurant->id])
            ->callAction('deliveryWeek', [
                'week_start' => $week1->toDateString(),
                'app_name' => 'Jahez',
                'orders_count' => '٢٠',
                'orders_value' => '٢٥٠.٢٥٠',
            ])
            ->assertHasNoActionErrors()
            ->assertSee('Delivery apps: weekly orders')
            ->assertSee('140')
            ->assertSee('0.500 JOD')
            ->assertSee('1,751.250 JOD');

        // hide_spend hides cost per delivery order cleanly.
        $restaurant->update(['hide_spend' => true]);
        $hiddenSummary = DeliveryOutcomes::summary($restaurant->fresh(), now('Asia/Amman')->subWeeks(8)->toDateString(), now('Asia/Amman')->toDateString());
        $this->assertSame('–', $hiddenSummary['cost_per_order_formatted']);

        Livewire::test(ViewClient::class, ['record' => $restaurant->id])
            ->assertSee('Delivery orders')
            ->assertSee('140')
            ->assertDontSee('Cost per delivery order')
            ->assertDontSee('0.500 JOD');
        $restaurant->update(['hide_spend' => false]);

        // Tenant isolation & Client::visibleTo scoping.
        [$otherAgency, $otherOwner] = $this->makeWorkspace('other-delivery-sp', 'en');
        $otherClient = Client::create([
            'agency_id' => $otherAgency->id,
            'name' => 'Other Restaurant',
            'vertical' => 'restaurant',
            'currency' => 'SAR',
            'status' => 'active',
        ]);
        $this->assertNull(DeliveryOutcomes::summary($otherClient, now()->subWeeks(8)->toDateString(), now()->toDateString()));
        $this->assertFalse(Client::visibleTo($otherOwner, $otherAgency)->whereKey($restaurant->id)->exists());

        // TeamRoles: analyst can view delivery orders but cannot see or run write actions.
        $analyst = User::factory()->create();
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $this->asTenant($agency, $analyst);
        Livewire::test(ViewClient::class, ['record' => $restaurant->id])
            ->assertSee('Delivery apps: weekly orders')
            ->assertActionHidden('deliveryWeek')
            ->assertActionHidden('deliverySheet');

        $weekRow = DeliveryWeek::where('client_id', $restaurant->id)->firstOrFail();
        Livewire::test(ViewClient::class, ['record' => $restaurant->id])
            ->call('removeDeliveryWeek', $weekRow->id)
            ->assertForbidden();
        $this->assertNotNull(DeliveryWeek::find($weekRow->id));

        // ViewAs + Client::visibleTo scoping & ReadOnlyGuard blocking writes on read-only workspace.
        $restrictedAnalyst = User::factory()->create();
        $agency->members()->attach($restrictedAnalyst->id, ['role' => 'analyst']);
        $otherScoped = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Scoped Cafe',
            'vertical' => 'restaurant',
            'currency' => 'JOD',
            'status' => 'active',
        ]);
        $otherScoped->users()->attach($owner->id);
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/desk?view_as=u:{$restrictedAnalyst->id}")->assertOk();
        $this->assertFalse(Client::visibleTo($owner, $agency)->whereKey($otherScoped->id)->exists());
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/desk?view_as=off")->assertOk();

        $agency->forceFill(['status' => 'trial', 'signup_source' => 'self_signup', 'trial_ends_at' => now()->subDays(2)])->save();
        $this->asTenant($agency, $owner);
        $this->assertFalse(ReadOnlyGuard::allows(new ViewClient, 'mountAction', ['deliveryWeek']));
        $this->assertFalse(ReadOnlyGuard::allows(new ViewClient, 'mountAction', ['deliverySheet']));
        Livewire::test(ViewClient::class, ['record' => $restaurant->id])
            ->call('removeDeliveryWeek', $weekRow->id);
        $this->assertNotNull(DeliveryWeek::find($weekRow->id), 'ReadOnlyGuard blocks writes when workspace is read-only');
    }

    public function test_sector_packs_migration_is_cleanly_reversible(): void
    {
        $migration = require database_path('migrations/2027_01_29_000001_sector_packs_permits_and_delivery.php');

        $this->assertTrue(Schema::hasTable('delivery_weeks'));
        $this->assertTrue(Schema::hasColumn('delivery_weeks', 'agency_id'));
        $this->assertTrue(Schema::hasColumn('client_licences', 'ad_name'));
        $this->assertTrue(Schema::hasColumns('clients', ['launch_project_name', 'launch_date']));

        $migration->down();

        $this->assertFalse(Schema::hasTable('delivery_weeks'));
        $this->assertFalse(Schema::hasColumn('client_licences', 'ad_name'));
        $this->assertFalse(Schema::hasColumn('clients', 'launch_project_name'));
        $this->assertFalse(Schema::hasColumn('clients', 'launch_date'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('delivery_weeks'));
        $this->assertTrue(Schema::hasColumn('delivery_weeks', 'agency_id'));
        $this->assertTrue(Schema::hasColumn('client_licences', 'ad_name'));
        $this->assertTrue(Schema::hasColumns('clients', ['launch_project_name', 'launch_date']));
    }
}
