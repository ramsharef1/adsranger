<?php

namespace App\Http\Middleware;

use App\Filament\Pages\PlanUsage;
use App\Filament\Pages\TrialEnded;
use App\Models\Agency;
use App\Support\Trial;
use App\Support\TrialNotice;
use Closure;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /app tenant middleware for the D-61 trial lifecycle (self-signup agencies only; others pass straight through).
 * - Read-only: create / edit pages (and the workspace settings page) don't open; the EX-50 style notice says why
 *   and the user lands on the list. Livewire calls are refused by App\Support\ReadOnlyGuard.
 * - Closed (`expired`): every page sends the user to "Your trial ended" (the sign-in notice), except Plan & usage
 *   and the export downloads (the owner can still take the workspace ZIP).
 */
class TrialLifecycleGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $agency = Filament::getTenant();
        if (! $agency instanceof Agency || ! Trial::isReadOnly($agency)) {
            return $next($request);
        }

        $name = (string) $request->route()?->getName();
        $page = $request->route()?->getControllerClass();

        if (Trial::isClosed($agency)) {
            if (str_starts_with($name, 'filament.app.export.') || in_array($page, [TrialEnded::class, PlanUsage::class], true)) {
                return $next($request);
            }

            return redirect(TrialEnded::getUrl(tenant: $agency));
        }

        if ($page && (is_subclass_of($page, CreateRecord::class) || is_subclass_of($page, EditRecord::class) || is_subclass_of($page, EditTenantProfile::class))) {
            TrialNotice::readOnly($agency, $request->user())->send();
            $index = method_exists($page, 'getResource') ? $page::getResource()::getUrl('index', tenant: $agency) : Dashboard::getUrl(tenant: $agency);

            return redirect($index);
        }

        return $next($request);
    }
}
