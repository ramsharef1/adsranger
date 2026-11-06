<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveAgencyCustomDomain;
use App\Models\Agency;
use App\Support\CustomDomainManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class Wave10CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_domain_configuration_activation_and_middleware_resolution(): void
    {
        $agency = Agency::create([
            'name' => 'Gulf Media Group',
            'slug' => 'gulf-media',
            'plan' => 'agency',
            'status' => 'active',
        ]);

        CustomDomainManager::configure($agency, 'https://Portal.GulfMedia.com/reports');
        $this->assertSame('portal.gulfmedia.com', $agency->custom_domain);
        $this->assertSame('pending', $agency->custom_domain_status);
        $this->assertNull(CustomDomainManager::resolveActiveAgency('portal.gulfmedia.com'));

        CustomDomainManager::activate($agency);
        $this->assertSame('active', $agency->custom_domain_status);
        $this->assertNotNull($agency->custom_domain_verified_at);

        $resolved = CustomDomainManager::resolveActiveAgency('portal.gulfmedia.com');
        $this->assertNotNull($resolved);
        $this->assertSame($agency->id, $resolved->id);

        $request = Request::create('https://portal.gulfmedia.com/r/sample-token', 'GET');
        $middleware = new ResolveAgencyCustomDomain();
        $middleware->handle($request, fn ($req) => response('ok'));

        $this->assertSame($agency->id, $request->attributes->get('current_agency')?->id);
    }
}