<?php

use App\Http\Controllers\ClientReportController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ReportUnsubscribeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Homepage: Arabic is the default, English at /en
Route::get('/', fn () => view('welcome', ['lang' => 'ar']));
Route::get('/en', fn () => view('welcome', ['lang' => 'en']));

// Public Website Phase 1: Waitlist, Ramadan 2027 planner, and XML sitemap
Route::get('/waitlist', [\App\Http\Controllers\PublicSiteController::class, 'waitlist'])-> defaults('lang', 'ar');
Route::get('/en/waitlist', [\App\Http\Controllers\PublicSiteController::class, 'waitlist'])->defaults('lang', 'en');
Route::post('/waitlist', [\App\Http\Controllers\PublicSiteController::class, 'submitWaitlist'])->defaults('lang', 'ar')->middleware('throttle:10,1');
Route::post('/en/waitlist', [\App\Http\Controllers\PublicSiteController::class, 'submitWaitlist'])->defaults('lang', 'en')->middleware('throttle:10,1');
Route::get('/ramadan-2027', [\App\Http\Controllers\PublicSiteController::class, 'ramadan'])->defaults('lang', 'ar');
Route::get('/en/ramadan-2027', [\App\Http\Controllers\PublicSiteController::class, 'ramadan'])->defaults('lang', 'en');
Route::get('/sitemap.xml', [\App\Http\Controllers\PublicSiteController::class, 'sitemap']);

// Legal pages: Arabic default, English under /en
foreach (['privacy', 'terms', 'data-deletion'] as $legalPage) {
    Route::get('/'.$legalPage, fn () => view('legal.page', ['lang' => 'ar', 'page' => $legalPage]));
    Route::get('/en/'.$legalPage, fn () => view('legal.page', ['lang' => 'en', 'page' => $legalPage]));
}

// #56 signed client links: read-only client report, no login
Route::get('/r/{token}', [ClientReportController::class, 'show'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{40}');
// #57 monthly PDF behind the same link
Route::get('/r/{token}/ads', [ClientReportController::class, 'ads'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{40}');
Route::get('/r/{token}/pdf', [ClientReportController::class, 'pdf'])->middleware('throttle:10,1')->where('token', '[A-Za-z0-9]{40}');

// #79 client-facing CSV behind the same link (follows hide_spend)
Route::get('/r/{token}/csv', [ClientReportController::class, 'csv'])->middleware('throttle:10,1')->where('token', '[A-Za-z0-9]{40}');

// #106 "Stop these emails" for one recipient of a scheduled report (signed; GET asks, POST records)
Route::get('/reports/unsubscribe/{schedule}', [ReportUnsubscribeController::class, 'show'])->middleware(['signed', 'throttle:30,1'])
    ->whereNumber('schedule')->name('reports.unsubscribe');
Route::post('/reports/unsubscribe/{schedule}', [ReportUnsubscribeController::class, 'store'])->middleware(['signed', 'throttle:30,1'])
    ->whereNumber('schedule');

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
