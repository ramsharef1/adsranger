<?php

namespace Tests\Feature;

use App\Filament\Pages\IntakeFormPage;
use App\Filament\Pages\LaunchCheckPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLicence;
use App\Models\User;
use App\Support\LaunchPreflight;
use App\Support\Seo\SafeFetcher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** R4: XS-11 campaign launch check, NB-22 / XS-37 printable client intake form. */
class R4mLaunchIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Bright Agency', 'slug' => 'acme-r4m', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Oasis Realty', 'currency' => 'SAR', 'status' => 'active']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        return [$agency, $owner, $c];
    }

    public function test_launch_check_lists_what_to_fix(): void
    {
        [, , $c] = $this->workspace();
        ClientLicence::create(['agency_id' => $c->agency_id, 'client_id' => $c->id, 'kind' => 'real_estate', 'number' => 'FAL-1200012345']);
        SafeFetcher::$resolver = fn () => '93.184.216.34';
        Http::fake(['oasis.example/*' => Http::response('<html lang="en"><head><title>Villas</title></head><body>No tags here</body></html>', 200, ['Content-Type' => 'text/html'])]);

        $r = collect(LaunchPreflight::run($c, 'https://oasis.example/villas?utm_source=meta', 'فلل للبيع بضمان 100% في الرياض'))->keyBy('key');
        $this->assertFalse($r['utm']['ok']);
        $this->assertStringContainsString('utm_medium', $r['utm']['message']);
        $this->assertTrue($r['page']['ok']);
        $this->assertFalse($r['language']['ok'], 'Arabic ad, English page');
        $this->assertSame('critical', $r['tracking']['level']);
        $this->assertFalse($r['policy']['ok']);
        $this->assertFalse($r['licence']['ok']);

        Livewire::test(LaunchCheckPage::class)->set('clientId', $c->id)->set('url', 'https://oasis.example/villas?utm_source=meta&utm_medium=paid&utm_campaign=x')
            ->set('adText', 'Villas for sale FAL-1200012345')->call('check')->assertSee('The licence number is in the ad.');
        SafeFetcher::$resolver = null;
    }

    public function test_intake_form_carries_the_agency_name_and_chosen_sections(): void
    {
        $this->workspace();
        Livewire::test(IntakeFormPage::class)->set('lang', 'ar')->assertSee('Bright Agency')->assertSee('نموذج بيانات العميل')->assertSee('اللهجة المفضلة')
            ->set('sections', ['goals'])->assertDontSee('اللهجة المفضلة')->assertDontSee('AdsRanger · نموذج');
    }
}
