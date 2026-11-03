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
