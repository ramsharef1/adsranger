<?php

namespace Tests\Feature;

use App\Http\Middleware\NoIndexPrivateRoutes;
use App\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tenant-route check (ADSR-040 §2): private routes carry noindex; agency slugs never shadow panel routes. */
class PrivateRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_login_pages_send_noindex(): void
    {
        $this->get('/app/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_pages_are_left_to_phase_one(): void
    {
        $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/en/privacy')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_private_prefix_matching(): void
    {
        foreach (['app', 'app/acme/desk', 'admin/plans', 'invite/abc', 'livewire-4aeeedbb/update', 'storage/x.png', 'up'] as $p) {
            $this->assertTrue(NoIndexPrivateRoutes::isPrivate($p), $p);
        }
        foreach (['', 'en', 'privacy', 'en/terms', 'calendar/ramadan-2027', 'apps', 'update'] as $p) {
            $this->assertFalse(NoIndexPrivateRoutes::isPrivate($p), $p);
        }
    }

    public function test_reserved_slugs_cover_every_panel_level_route(): void
    {
        // Every first segment under /app that is not a tenant must be reserved.
        $panelLevel = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($u) => str_starts_with($u, 'app/') && ! str_starts_with($u, 'app/{tenant'))
            ->map(fn ($u) => explode('/', $u)[1])->unique()->values();

        $this->assertNotEmpty($panelLevel);
        foreach ($panelLevel as $seg) {
            $this->assertContains($seg, config('adsranger.reserved_slugs'), "/app/$seg is not reserved");
        }
    }

    public function test_reserved_names_get_a_safe_slug(): void
    {
        $this->assertSame('login-agency', Agency::uniqueSlug('Login'));
        $this->assertSame('connect-agency', Agency::uniqueSlug('Connect'));
        $this->assertSame('acme', Agency::uniqueSlug('Acme'));
    }
}
