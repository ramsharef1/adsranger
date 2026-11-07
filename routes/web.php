<?php

use App\Http\Controllers\AgencyLogoController;
use App\Http\Controllers\ClientOnboardingController;
use App\Http\Controllers\ClientReportController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ReportUnsubscribeController;
use App\Http\Controllers\StudioBriefController;
use App\Http\Middleware\PublicTrailingSlash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public site (Website P-0 / P-5): Arabic at the root, English under /en; `/en/` is the English home (301 from /en).
Route::middleware(PublicTrailingSlash::class)->group(function () {
    Route::get('/', fn () => view('welcome', ['lang' => 'ar']));
    Route::get('/en', fn () => view('welcome', ['lang' => 'en']));

    Route::get('/waitlist', [PublicSiteController::class, 'waitlist'])->defaults('lang', 'ar');
    Route::get('/en/waitlist', [PublicSiteController::class, 'waitlist'])->defaults('lang', 'en');
    Route::post('/waitlist', [PublicSiteController::class, 'submitWaitlist'])->defaults('lang', 'ar');
    Route::post('/en/waitlist', [PublicSiteController::class, 'submitWaitlist'])->defaults('lang', 'en');

    Route::get('/ramadan-2027', [PublicSiteController::class, 'ramadan'])->defaults('lang', 'ar');
    Route::get('/en/ramadan-2027', [PublicSiteController::class, 'ramadan'])->defaults('lang', 'en');
    Route::get('/tools/ramadan-budget-calculator', [PublicSiteController::class, 'ramadanCalculator'])->defaults('lang', 'ar');
    Route::get('/en/tools/ramadan-budget-calculator', [PublicSiteController::class, 'ramadanCalculator'])->defaults('lang', 'en');

    Route::get('/pricing', [PublicSiteController::class, 'pricing'])->defaults('lang', 'ar');
    Route::get('/en/pricing', [PublicSiteController::class, 'pricing'])->defaults('lang', 'en');

    Route::get('/integrations', [PublicSiteController::class, 'integrations'])->defaults('lang', 'ar');
    Route::get('/en/integrations', [PublicSiteController::class, 'integrations'])->defaults('lang', 'en');

    Route::get('/demo-report', [PublicSiteController::class, 'demoReport'])->defaults('lang', 'ar');
    Route::get('/en/demo-report', [PublicSiteController::class, 'demoReport'])->defaults('lang', 'en');

    Route::get('/mena-calendar', [PublicSiteController::class, 'menaCalendar'])->defaults('lang', 'ar');
    Route::get('/en/mena-calendar', [PublicSiteController::class, 'menaCalendar'])->defaults('lang', 'en');

    Route::get('/about', [PublicSiteController::class, 'about'])->defaults('lang', 'ar');
    Route::get('/en/about', [PublicSiteController::class, 'about'])->defaults('lang', 'en');
    Route::get('/contact', [PublicSiteController::class, 'contact'])->defaults('lang', 'ar');
    Route::get('/en/contact', [PublicSiteController::class, 'contact'])->defaults('lang', 'en');
    Route::get('/partners', [PublicSiteController::class, 'partners'])->defaults('lang', 'ar');
    Route::get('/en/partners', [PublicSiteController::class, 'partners'])->defaults('lang', 'en');
    Route::post('/partners', [PublicSiteController::class, 'submitPartners'])->defaults('lang', 'ar');
    Route::post('/en/partners', [PublicSiteController::class, 'submitPartners'])->defaults('lang', 'en');
    Route::get('/solutions/business-owners', [PublicSiteController::class, 'businessOwners'])->defaults('lang', 'ar');
    Route::get('/en/solutions/business-owners', [PublicSiteController::class, 'businessOwners'])->defaults('lang', 'en');

    Route::get('/security', [PublicSiteController::class, 'security'])->defaults('lang', 'ar');
    Route::get('/en/security', [PublicSiteController::class, 'security'])->defaults('lang', 'en');

    // Sitemap index (R-13) → one child for the pages (R-19 adds the five pages live since 7 Nov).
    Route::get('/sitemap.xml', [PublicSiteController::class, 'sitemap']);
    Route::get('/sitemaps/pages.xml', [PublicSiteController::class, 'sitemapPages']);

    // Legal pages: Arabic default, English under /en
    foreach (['privacy', 'terms', 'data-deletion'] as $legalPage) {
        Route::get('/'.$legalPage, fn () => view('legal.page', ['lang' => 'ar', 'page' => $legalPage]));
        Route::get('/en/'.$legalPage, fn () => view('legal.page', ['lang' => 'en', 'page' => $legalPage]));
    }
});

// #56 signed client links: read-only client report, no login
// #176 agency logo on client reports (random file name, see App\Support\AgencyLogo)
Route::get('/brand/agency/{file}', [AgencyLogoController::class, 'show'])
    ->middleware('throttle:120,1')->where('file', '[A-Za-z0-9]{40}\.(png|svg)')->name('agency.logo');
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

// #54 Creative Studio signed client approval links (no login required)
Route::get('/studio/brief/{token}', [StudioBriefController::class, 'show'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{40}');
Route::post('/studio/brief/{token}', [StudioBriefController::class, 'decide'])->middleware('throttle:20,1')->where('token', '[A-Za-z0-9]{40}');

// #105 Client onboarding & access request links + #77/#115 signed JSON feed for Looker Studio / Sheets / Webhooks
Route::get('/onboard/{token}', [ClientOnboardingController::class, 'show'])
    ->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{32,64}')->name('client.onboard.show');
Route::post('/onboard/{token}', [ClientOnboardingController::class, 'submit'])
    ->middleware('throttle:20,1')->where('token', '[A-Za-z0-9]{32,64}')->name('client.onboard.submit');
Route::get('/api/v1/export/{token}', [ClientOnboardingController::class, 'exportFeed'])
    ->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{32,64}')->name('api.export.feed');

// Wave 21 public growth pages (E2 `7c3df2e`) held back 2026-11-07 (D-168): the partner directory (view kept as
// public/partners-held; /partners is now the P-25 design-partner page, D-219), case studies and
// the uplift calculator showed invented agencies, results and an 18% figure. Views kept for a rebuild from real data.
foreach (['calculator', 'resources'] as $held) {
    Route::redirect('/'.$held, '/waitlist', 302);
    Route::redirect('/en/'.$held, '/en/waitlist', 302);
}
