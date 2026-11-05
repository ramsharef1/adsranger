<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * #74 per-agency rule for /app: when the workspace has "Require two-step verification for all members" on,
 * a member without it is sent to the set-up screen before any page of that workspace opens (after set-up they
 * continue to where they were going). Workspaces without the rule, and panel-level pages (profile, new agency),
 * are not affected. Used as the /app panel's "MFA required" middleware, so it runs on every panel page route.
 */
class EnsureAgencyTwoStep
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        $tenant = static::tenant($request);

        if ($user && $tenant?->require_two_factor && ! MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        return $next($request);
    }

    protected static function tenant(Request $request): ?Agency
    {
        $tenant = Filament::getTenant();
        if ($tenant instanceof Agency) {
            return $tenant;
        }
        $param = $request->route('tenant');
        if ($param instanceof Agency) {
            return $param;
        }

        return is_string($param) ? Agency::query()->where('slug', $param)->first() : null;
    }
}
