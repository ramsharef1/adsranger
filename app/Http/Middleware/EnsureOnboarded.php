<?php

namespace App\Http\Middleware;

use App\Filament\Pages\OnboardingPage;
use App\Models\Agency;
use App\Support\Onboarding;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** D-188: the owner of a workspace that still needs onboarding lands on /app/{slug}/onboarding from every page. */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $agency = Filament::getTenant();
        if (! $agency instanceof Agency || ! Onboarding::blocks($agency, $request->user())) {
            return $next($request);
        }
        if ($request->route()?->getControllerClass() === OnboardingPage::class) {
            return $next($request);
        }

        return redirect(OnboardingPage::getUrl(tenant: $agency));
    }
}
