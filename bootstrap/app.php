<?php

use App\Http\Middleware\NoIndexPrivateRoutes;
use App\Support\ErrorRef;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Private routes are never indexable (ADSR-040 §2, tenant-route check 2026-11-05).
        $middleware->append(NoIndexPrivateRoutes::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // UX-9: the reference shown on 5xx pages is in every logged error, so support can find it.
        $exceptions->context(fn () => ['ref' => app(ErrorRef::class)->id()]);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
