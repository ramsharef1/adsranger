<?php

namespace App\Providers;

use App\Http\Middleware\AiConnectorGuard;
use App\Http\Middleware\RecordAiConnection;
use App\Mcp\Servers\AdsRangerServer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;
use Laravel\Passport\Passport;

/**
 * AI Workspace M1 slice 2 (D-283, D-284): the AdsRanger connector at mcp.adsranger.com, OAuth 2.1 with dynamic client
 * registration on adsranger.com/oauth (Passport, only the routes we use), and the consent screen with the ownership tick.
 */
class AiWorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Passport::ignoreRoutes();
    }

    public function boot(): void
    {
        config(['mcp.authorization_server' => rtrim((string) config('app.url'), '/')]);
        Passport::tokensExpireIn(now()->addMinutes((int) config('ai_workspace.token_minutes', 60)));
        Passport::refreshTokensExpireIn(now()->addDays((int) config('ai_workspace.refresh_days', 30)));
        Passport::authorizationView('ai.connect');

        Route::prefix('oauth')->name('passport.')->group(function () {
            Route::post('token', [AccessTokenController::class, 'issueToken'])->middleware('throttle:30,1')->name('token');
            Route::get('authorize', [AuthorizationController::class, 'authorize'])->middleware('web')->name('authorizations.authorize');
            Route::middleware(['web', 'auth'])->group(function () {
                Route::post('authorize', [ApproveAuthorizationController::class, 'approve'])->middleware(RecordAiConnection::class)->name('authorizations.approve');
                Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
            });
        });
        Route::middleware('throttle:10,1')->group(fn () => Mcp::oauthRoutes('oauth'));

        $host = (string) config('ai_workspace.mcp_host');
        Route::domain($host)->middleware(['auth:api', AiConnectorGuard::class])->group(fn () => Mcp::web('/', AdsRangerServer::class));
        // The connector host serves the connector and its OAuth metadata only, never the website.
        Route::domain($host)->group(fn () => Route::any('{any}', fn () => response()->json(['error' => 'Not found'], 404))->where('any', '^(?!\.well-known/).*'));
    }
}
