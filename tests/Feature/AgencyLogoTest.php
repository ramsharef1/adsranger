<?php

namespace Tests\Feature;

use App\Filament\Pages\Tenancy\EditAgencyProfile;
use App\Mail\ScheduledReportMail;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Support\AgencyLogo;
use App\Support\DemoData;
use App\Support\MonthlyReport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** #176 (FR-019, D-105): agency logo on client links, the monthly PDF and report emails, all tiers. */
class AgencyLogoTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Client $client;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->agency = Agency::create(['name' => 'Acme Agency', 'slug' => 'acme', 'plan' => 'starter', 'status' => 'active', 'language' => 'en']);
        $this->owner = User::factory()->create();
        $this->agency->members()->attach($this->owner->id, ['role' => 'owner']);
        DemoData::seed($this->agency, 40);
        $this->client = Client::where('agency_id', $this->agency->id)->orderBy('id')->first();
    }

    private function png(int $width, int $height = 120): string
    {
        $img = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private function storeLogo(string $ext, string $contents): string
    {
        $path = AgencyLogo::DIRECTORY.'/'.Str::random(40).'.'.$ext;
        Storage::disk('local')->put($path, $contents);
        $this->agency->forceFill(['logo_path' => $path])->save();

        return $path;
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_without_a_logo_reports_show_the_agency_name(): void
    {
        $link = ClientLink::issue($this->client, $this->owner);

        $this->get('/r/'.$link->token)->assertOk()
            ->assertDontSee('class="agency-logo"', false)
            ->assertSee('Prepared by Acme Agency')
            ->assertSee('Powered by', false)->assertSee('rel="nofollow noopener"', false);
        $this->assertNull(AgencyLogo::url($this->agency));
    }

    public function test_png_logo_shows_on_client_link_gallery_pdf_and_email(): void
    {
        $path = $this->storeLogo('png', $this->png(600));
        $link = ClientLink::issue($this->client, $this->owner);
        $url = AgencyLogo::url($this->agency->fresh());

        $this->get('/r/'.$link->token)->assertOk()->assertSee($url, false)->assertSee('height="32"', false);
        $this->get('/r/'.$link->token.'/ads')->assertOk()->assertSee($url, false);

        $html = view('reports.monthly-pdf', MonthlyReport::data($this->client->fresh(), now()->subMonthNoOverflow()->format('Y-m'), 'en'))->render();
        $this->assertStringContainsString(Storage::disk('local')->path($path), $html);
        $this->assertStringContainsString('height: 48px', $html);
        $this->assertStringStartsWith('%PDF-', MonthlyReport::pdf($this->client->fresh(), now()->subMonthNoOverflow()->format('Y-m'), 'ar'));

        $schedule = ReportSchedule::create(['agency_id' => $this->agency->id, 'client_id' => $this->client->id, 'monthly_day' => 10, 'lang' => 'en',
            'recipients' => ['a@example.com'], 'attach_pdf' => false, 'include_link' => false, 'active' => true]);
        $mail = (new ScheduledReportMail($schedule, 'monthly', now()->subMonthNoOverflow()->format('Y-m')))->render();
        $this->assertStringContainsString($url, $mail);
        $this->assertStringContainsString('Powered by <bdi dir="ltr">AdsRanger</bdi>', $mail);
    }

    public function test_logo_is_served_with_safe_headers_and_unknown_files_are_not_found(): void
    {
        $path = $this->storeLogo('svg', '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="100"><rect width="400" height="100"/></svg>');

        $this->get('/brand/agency/'.basename($path))->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        $this->get('/brand/agency/'.Str::random(40).'.png')->assertNotFound();
        $this->get('/brand/agency/../.env')->assertNotFound();
    }

    public function test_svg_logo_is_not_used_in_emails(): void
    {
        $this->storeLogo('svg', '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="100"/>');
        $this->assertNotNull(AgencyLogo::url($this->agency->fresh()));
        $this->assertNull(AgencyLogo::emailUrl($this->agency->fresh()));
    }

    public function test_upload_rules_from_experience_spec_b(): void
    {
        $this->assertNull(EditAgencyProfile::logoProblem($this->upload('ok.png', $this->png(600))));
        $this->assertSame('Use an image at least 400 px wide.', EditAgencyProfile::logoProblem($this->upload('small.png', $this->png(200))));
        $this->assertSame('The file is too large: the limit is 200 KB.', EditAgencyProfile::logoProblem($this->upload('big.png', str_repeat('x', 205 * 1024))));
        $this->assertNull(EditAgencyProfile::logoProblem($this->upload('ok.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="400"><path d="M0 0h10" fill="#0F766E"/></svg>')));
        foreach ([
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="http://127.0.0.1/secret"/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><image xlink:href="file:///etc/passwd"/></svg>',
        ] as $bad) {
            $this->assertNotNull(EditAgencyProfile::logoProblem($this->upload('bad.svg', $bad)), $bad);
        }
    }

    public function test_owner_uploads_a_logo_in_agency_settings(): void
    {
        Storage::fake('tmp-for-tests');
        $this->agency->forceFill(['timezone' => 'Asia/Amman', 'currency' => 'USD'])->save();
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);

        Livewire::test(EditAgencyProfile::class)
            ->set('data.logo_path', UploadedFile::fake()->createWithContent('logo.png', $this->png(600)))
            ->call('save')
            ->assertHasNoErrors();

        $path = (string) $this->agency->fresh()->logo_path;
        $this->assertMatchesRegularExpression('#^agency-logos/[A-Za-z0-9]{40}\.png$#', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertNotNull(AgencyLogo::url($this->agency->fresh()));
    }

    public function test_settings_page_rejects_a_narrow_png(): void
    {
        Storage::fake('tmp-for-tests');
        $this->agency->forceFill(['timezone' => 'Asia/Amman', 'currency' => 'USD'])->save();
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);

        Livewire::test(EditAgencyProfile::class)
            ->set('data.logo_path', UploadedFile::fake()->createWithContent('logo.png', $this->png(200)))
            ->call('save')
            ->assertHasErrors(['data.logo_path']);

        $this->assertNull($this->agency->fresh()->logo_path);
    }
}
