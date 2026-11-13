<?php

namespace App\Http\Middleware;

use App\Filament\Pages\AiConnectionsPage;
use App\Filament\Pages\AiKeysPage;
use App\Filament\Pages\ClientBrainPage;
use App\Filament\Pages\FreeLiteView;
use App\Filament\Pages\MyDevices;
use App\Filament\Pages\MyNotifications;
use App\Filament\Pages\Networks;
use App\Filament\Pages\NightShiftPage;
use App\Filament\Pages\OnboardingPage;
use App\Filament\Pages\PlanUsage;
use App\Filament\Pages\Tenancy\EditAgencyProfile;
use App\Filament\Pages\WorkspaceActivity;
use App\Models\Agency;
use App\Support\AiWorkspace\FreePlan;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Free plan «الباقة المجانية» (D-286, spec v2 §3): the app is the lite view, the client brain (read only), AI connections,
 * and what a workspace needs to set itself up (its one client, connecting one ad account, settings). Every other page
 * opens the lite view, which says what the paid plans add. Exports, reports and dashboards are paid only.
 */
class FreePlanFence
{
    /** Pages a Free workspace may open. */
    protected const PAGES = [FreeLiteView::class, ClientBrainPage::class, AiConnectionsPage::class, AiKeysPage::class, NightShiftPage::class,
        MyDevices::class, MyNotifications::class, WorkspaceActivity::class, PlanUsage::class, EditAgencyProfile::class, OnboardingPage::class, Networks::class];

    /** Resource folders a Free workspace may open (its client, its connection, its ad account, its team page). */
    protected const RESOURCES = ['Clients', 'Connections', 'AdAccounts', 'Members', 'Invitations'];

    public function handle(Request $request, Closure $next): Response
    {
        $agency = Filament::getTenant();
        if (! $agency instanceof Agency || ! FreePlan::isFree($agency)) {
            return $next($request);
        }
        $page = (string) $request->route()?->getControllerClass();
        if (in_array($page, self::PAGES, true)) {
            return $next($request);
        }
        foreach (self::RESOURCES as $r) {
            if (str_starts_with($page, "App\\Filament\\Resources\\$r\\")) {
                return $next($request);
            }
        }

        return redirect(FreeLiteView::getUrl(tenant: $agency));
    }
}
