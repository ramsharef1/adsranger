<?php

namespace App\Http\Middleware;

use App\Support\CustomDomainManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAgencyCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $agency = CustomDomainManager::resolveActiveAgency($request->getHost());
        if ($agency) {
            $request->attributes->set('current_agency', $agency);
        }

        return $next($request);
    }
}