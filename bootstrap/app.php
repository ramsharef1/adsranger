<?php

use App\Http\Middleware\NoIndexPrivateRoutes;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ErrorRef;
use App\Support\Links\Reclaim;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Private routes are never indexable (ADSR-040 §2, tenant-route check 2026-11-05).
        $middleware->append(NoIndexPrivateRoutes::class);
        // Website W2 (2026-11-08): baseline security headers on every response.
        $middleware->append(SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: ['collect/*']);
        // AI Workspace connect (OAuth): a signed-out person is sent to the app login, then back to the consent screen.
        $middleware->redirectGuestsTo(fn () => route('filament.app.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // UX-9: the reference shown on 5xx pages is in every logged error, so support can find it.
        $exceptions->context(fn () => ['ref' => app(ErrorRef::class)->id()]);
        // BX-09: every 404 is logged (path + hit count) for link reclamation, and 301s added in /admin are served here.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => Reclaim::onNotFound($request));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson() || $request->getHost() === config('ai_workspace.mcp_host'),
        );
    })->create();
