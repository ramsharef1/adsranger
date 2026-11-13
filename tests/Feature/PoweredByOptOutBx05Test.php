<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Support\PoweredBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** cl101 BX-05 (D-278 / D-280): "Powered by AdsRanger" on client reports, on by default, the agency can switch it off. */
class PoweredByOptOutBx05Test extends TestCase
{
    use RefreshDatabase;

    public function test_line_is_on_by_default_links_nofollow_and_the_agency_can_switch_it_off(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-pb', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman']);
        $this->assertTrue(PoweredBy::on($agency->fresh()));

        app()->setLocale('en');
        $html = PoweredBy::html($agency);
        $this->assertStringContainsString('Powered by', $html);
        $this->assertStringContainsString('rel="nofollow noopener"', $html);
        $this->assertStringNotContainsString('<a ', PoweredBy::html($agency, false), 'report emails get plain text');

        app()->setLocale('ar');
        $this->assertStringContainsString('مدعوم من', PoweredBy::html($agency));

        $agency->update(['report_powered_by' => false]);
        $this->assertFalse(PoweredBy::on($agency->fresh()));
        $this->assertSame('', PoweredBy::html($agency->fresh()));
    }
}
