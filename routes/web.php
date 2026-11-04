<?php

use Illuminate\Support\Facades\Route;

// Homepage: Arabic is the default, English at /en
Route::get('/', fn () => view('welcome', ['lang' => 'ar']));
Route::get('/en', fn () => view('welcome', ['lang' => 'en']));

// Legal pages: Arabic default, English under /en
foreach (['privacy', 'terms', 'data-deletion'] as $legalPage) {
    Route::get('/'.$legalPage, fn () => view('legal.page', ['lang' => 'ar', 'page' => $legalPage]));
    Route::get('/en/'.$legalPage, fn () => view('legal.page', ['lang' => 'en', 'page' => $legalPage]));
}

// Agency invitations
Route::get('/invite/{token}', [\App\Http\Controllers\InvitationController::class, 'show']);
Route::post('/invite/{token}', [\App\Http\Controllers\InvitationController::class, 'accept'])->middleware('throttle:10,1');

// Network OAuth connect (guests are sent to /app/login inside the controller)
Route::get('/app/connect/{network}/start', function (\Illuminate\Http\Request $r, string $network) {
    return $r->user() ? app(\App\Http\Controllers\ConnectController::class)->start($r, $network) : redirect()->guest('/app/login');
})->where('network', '[a-z0-9_]+');
Route::get('/app/connect/{network}/callback', function (\Illuminate\Http\Request $r, string $network) {
    return $r->user() ? app(\App\Http\Controllers\ConnectController::class)->callback($r, $network) : redirect()->guest('/app/login');
})->where('network', '[a-z0-9_]+');
