<?php

use App\Http\Controllers\ClientReportController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\InvitationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Homepage: Arabic is the default, English at /en
Route::get('/', fn () => view('welcome', ['lang' => 'ar']));
Route::get('/en', fn () => view('welcome', ['lang' => 'en']));

// Legal pages: Arabic default, English under /en
foreach (['privacy', 'terms', 'data-deletion'] as $legalPage) {
    Route::get('/'.$legalPage, fn () => view('legal.page', ['lang' => 'ar', 'page' => $legalPage]));
    Route::get('/en/'.$legalPage, fn () => view('legal.page', ['lang' => 'en', 'page' => $legalPage]));
}

// #56 signed client links: read-only client report, no login
Route::get('/r/{token}', [ClientReportController::class, 'show'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{40}');
// #57 monthly PDF behind the same link
Route::get('/r/{token}/pdf', [ClientReportController::class, 'pdf'])->middleware('throttle:10,1')->where('token', '[A-Za-z0-9]{40}');

// Agency invitations
Route::get('/invite/{token}', [InvitationController::class, 'show']);
Route::post('/invite/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1');

// Network OAuth connect (guests are sent to /app/login inside the controller)
Route::get('/app/connect/{network}/start', function (Request $r, string $network) {
    return $r->user() ? app(ConnectController::class)->start($r, $network) : redirect()->guest('/app/login');
})->where('network', '[a-z0-9_]+');
Route::get('/app/connect/{network}/callback', function (Request $r, string $network) {
    return $r->user() ? app(ConnectController::class)->callback($r, $network) : redirect()->guest('/app/login');
})->where('network', '[a-z0-9_]+');
