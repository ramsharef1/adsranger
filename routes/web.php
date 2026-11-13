<?php

use App\Http\Controllers\AgencyLogoController;
use App\Http\Controllers\ClientOnboardingController;
use App\Http\Controllers\ClientReportController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeadDeskController;
use App\Http\Controllers\MagicLoginController;
use App\Http\Controllers\PublicAttributionCollectorController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ReachLandingController;
use App\Http\Controllers\ReachPortalController;
use App\Http\Controllers\ReachPostbackController;
use App\Http\Controllers\ReachServeController;
use App\Http\Controllers\ReachToolController;
use App\Http\Controllers\ReportUnsubscribeController;
use App\Http\Controllers\StudioBriefController;
use App\Http\Middleware\PublicTrailingSlash;
use App\Http\Middleware\ReachEnabled;
use App\Http\Middleware\ToolAbuseGuard;
use App\Http\Middleware\TrackOrigin;
use App\Support\Ops\Heartbeat;
use App\Support\Seo\PublicCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public site (Website P-0 / P-5): Arabic at the root, English under /en; `/en/` is the English home (301 from /en).

// EG-03 (D-289): scheduler heartbeat for the outside watcher. 200 while fresh, 503 when the scheduler stopped. No data.
Route::get('/up/heartbeat', function () {
    $s = Heartbeat::status();

    return response()->json($s, $s['ok'] ? 200 : 503)->header('Cache-Control', 'no-store');
})->middleware('throttle:30,1');

Route::middleware([PublicTrailingSlash::class, TrackOrigin::class])->group(function () {
    Route::get('/', fn () => view('welcome', ['lang' => 'ar']));
    Route::get('/en', fn () => view('welcome', ['lang' => 'en']));

    Route::get('/waitlist', [PublicSiteController::class, 'waitlist'])->defaults('lang', 'ar');
    Route::get('/en/waitlist', [PublicSiteController::class, 'waitlist'])->defaults('lang', 'en');
    Route::post('/waitlist', [PublicSiteController::class, 'submitWaitlist'])->defaults('lang', 'ar');
    Route::post('/en/waitlist', [PublicSiteController::class, 'submitWaitlist'])->defaults('lang', 'en');

    Route::get('/ramadan-2027', [PublicSiteController::class, 'ramadan'])->defaults('lang', 'ar');
    Route::get('/en/ramadan-2027', [PublicSiteController::class, 'ramadan'])->defaults('lang', 'en');
    Route::get('/tools', [PublicSiteController::class, 'toolsHub'])->defaults('lang', 'ar');
    Route::get('/en/tools', [PublicSiteController::class, 'toolsHub'])->defaults('lang', 'en');
    Route::get('/tools/ramadan-budget-calculator', [PublicSiteController::class, 'ramadanCalculator'])->defaults('lang', 'ar');
    Route::get('/en/tools/ramadan-budget-calculator', [PublicSiteController::class, 'ramadanCalculator'])->defaults('lang', 'en');
    // Free tools (SEO slice 0 step 0.4 + D-90 ad tools): reachable for checks, noindex until config('seo_tools.public').
    foreach (config('seo_tools.tools') as $tool) {
        Route::get('/tools/'.$tool, [PublicSiteController::class, 'seoTool'])->defaults('lang', 'ar')->defaults('tool', $tool);
        Route::get('/en/tools/'.$tool, [PublicSiteController::class, 'seoTool'])->defaults('lang', 'en')->defaults('tool', $tool);
    }
    // Growth O-339 (12 Nov): renamed slugs keep their old URLs as 301s.
    // BX-09: the list lives in config/redirects.php so the reclamation board and its test check every rename.
    foreach (config('redirects.renames') as $old => $new) {
        Route::permanentRedirect($old, $new);
        Route::permanentRedirect('/en'.$old, '/en'.$new);
    }
    Route::post('/tools/hreflang-checker/run', [PublicSiteController::class, 'hreflangRun'])->middleware(['throttle:30,1', ToolAbuseGuard::class]);
    Route::post('/tools/page-audit/run', [PublicSiteController::class, 'pageAuditRun'])->middleware(['throttle:30,1', ToolAbuseGuard::class]);
    Route::post('/tools/ad-pixel-checker/run', [PublicSiteController::class, 'capiPixelRun'])->middleware(['throttle:30,1', ToolAbuseGuard::class]);
    Route::post('/tools/llms-txt-generator/check', [PublicSiteController::class, 'llmsTxtCheck'])->middleware(['throttle:30,1', ToolAbuseGuard::class]);
    Route::post('/tools/robots-sitemap-checker/run', [PublicSiteController::class, 'robotsSitemapRun'])->middleware(['throttle:30,1', ToolAbuseGuard::class]);
    Route::post('/tools/audit-report-request', [PublicSiteController::class, 'requestAuditEmail'])->defaults('lang', 'ar')->middleware('throttle:10,1');
    Route::post('/en/tools/audit-report-request', [PublicSiteController::class, 'requestAuditEmail'])->defaults('lang', 'en')->middleware('throttle:10,1');

    Route::get('/pricing', [PublicSiteController::class, 'pricing'])->defaults('lang', 'ar');
    Route::get('/en/pricing', [PublicSiteController::class, 'pricing'])->defaults('lang', 'en');

    Route::get('/integrations', [PublicSiteController::class, 'integrations'])->defaults('lang', 'ar');
    foreach (['features', 'reports', 'studio', 'search', 'ai'] as $prodSlug) {
        Route::get('/'.$prodSlug, [PublicSiteController::class, 'productPage'])->defaults('lang', 'ar')->defaults('slug', $prodSlug);
        Route::get('/en/'.$prodSlug, [PublicSiteController::class, 'productPage'])->defaults('lang', 'en')->defaults('slug', $prodSlug);
    }
    foreach (array_keys(PublicCatalog::platforms()) as $platSlug) {
        Route::get('/integrations/'.$platSlug, [PublicSiteController::class, 'platformPage'])->defaults('lang', 'ar')->defaults('platform', $platSlug);
        Route::get('/en/integrations/'.$platSlug, [PublicSiteController::class, 'platformPage'])->defaults('lang', 'en')->defaults('platform', $platSlug);
    }
    Route::get('/glossary', [PublicSiteController::class, 'glossaryIndex'])->defaults('lang', 'ar');
    Route::get('/en/glossary', [PublicSiteController::class, 'glossaryIndex'])->defaults('lang', 'en');
    foreach (array_keys(PublicCatalog::glossary()) as $termSlug) {
        Route::get('/glossary/'.$termSlug, [PublicSiteController::class, 'glossaryTerm'])->defaults('lang', 'ar')->defaults('term', $termSlug);
        Route::get('/en/glossary/'.$termSlug, [PublicSiteController::class, 'glossaryTerm'])->defaults('lang', 'en')->defaults('term', $termSlug);
    }
    Route::get('/guides', [PublicSiteController::class, 'guidesIndex'])->defaults('lang', 'ar');
    Route::get('/en/guides', [PublicSiteController::class, 'guidesIndex'])->defaults('lang', 'en');
    foreach (array_keys(PublicCatalog::guides()) as $guideSlug) {
        Route::get('/guides/'.$guideSlug, [PublicSiteController::class, 'guidesShow'])->defaults('lang', 'ar')->defaults('slug', $guideSlug);
        Route::get('/en/guides/'.$guideSlug, [PublicSiteController::class, 'guidesShow'])->defaults('lang', 'en')->defaults('slug', $guideSlug);
    }
    foreach (array_keys(PublicCatalog::solutionsAndRoles()) as $srKey) {
        Route::get('/'.$srKey, [PublicSiteController::class, 'solutionOrRolePage'])->defaults('lang', 'ar')->defaults('pageKey', $srKey);
        Route::get('/en/'.$srKey, [PublicSiteController::class, 'solutionOrRolePage'])->defaults('lang', 'en')->defaults('pageKey', $srKey);
    }
    Route::get('/blog', [PublicSiteController::class, 'blogIndex'])->defaults('lang', 'ar');
    Route::get('/en/blog', [PublicSiteController::class, 'blogIndex'])->defaults('lang', 'en');
    foreach (array_keys(PublicCatalog::posts()) as $postSlug) {
        Route::get('/blog/'.$postSlug, [PublicSiteController::class, 'blogShow'])->defaults('lang', 'ar')->defaults('slug', $postSlug);
        Route::get('/en/blog/'.$postSlug, [PublicSiteController::class, 'blogShow'])->defaults('lang', 'en')->defaults('slug', $postSlug);
    }
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

    Route::get('/llms.txt', [PublicSiteController::class, 'llmsTxt']);
    Route::get('/data', [PublicSiteController::class, 'data'])->defaults('lang', 'ar');
    Route::get('/data/{file}', [PublicSiteController::class, 'dataFile'])->where('file', '[A-Za-z0-9-]+\.(csv|json|md|txt)');
    Route::get('/en/data', [PublicSiteController::class, 'data'])->defaults('lang', 'en');
    Route::get('/accessibility', [PublicSiteController::class, 'accessibility'])->defaults('lang', 'ar');
    Route::get('/en/accessibility', [PublicSiteController::class, 'accessibility'])->defaults('lang', 'en');
    Route::get('/bot', [PublicSiteController::class, 'bot'])->defaults('lang', 'ar');
    Route::get('/en/bot', [PublicSiteController::class, 'bot'])->defaults('lang', 'en');
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
// FR-REACH (module reach, off): every route 404s while the module is off, and is noindex.
Route::middleware([ReachEnabled::class])->group(function () {
    Route::match(['get', 'post'], '/reach/v1/postback/{token}', ReachPostbackController::class)
        ->middleware('throttle:600,1')->where('token', '[A-Za-z0-9]{40}');
    // R-2: stateless serve endpoints (no cookies, CORS-open), web tag, AI feed.
    Route::get('/reach/v1/tag.js', [ReachServeController::class, 'tag'])->middleware('throttle:600,1');
    Route::get('/reach/v1/serve', [ReachServeController::class, 'serve'])->middleware('throttle:600,1');
    Route::get('/reach/v1/offerwall', [ReachServeController::class, 'offerwall'])->middleware('throttle:300,1');
    Route::get('/reach/v1/click', [ReachServeController::class, 'click'])->middleware('throttle:120,1');
    Route::post('/reach/v1/copy', [ReachServeController::class, 'copy'])->middleware('throttle:60,1');
    Route::get('/reach/v1/ai-feed', [ReachServeController::class, 'aiFeed'])->middleware('throttle:300,1');
    Route::get('/reach/v1/llms/{ref}.txt', [ReachServeController::class, 'llms'])->middleware('throttle:300,1')->where('ref', '[a-z0-9]{4,16}');
    // R-2: landing + publisher portal.
    Route::get('/reach', ReachLandingController::class)->defaults('lang', 'ar')->middleware('throttle:60,1');
    Route::get('/en/reach', ReachLandingController::class)->defaults('lang', 'en')->middleware('throttle:60,1');
    // Sign-in is checked in the controller, after the module gate, so a signed-out visitor also gets 404 while off.
    Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('/reach/portal', [ReachPortalController::class, 'show'])->defaults('lang', 'ar');
        Route::get('/en/reach/portal', [ReachPortalController::class, 'show'])->defaults('lang', 'en');
        Route::get('/reach/portal/stats', [ReachPortalController::class, 'stats']);
        Route::get('/en/reach/portal/stats', [ReachPortalController::class, 'stats']);
        Route::post('/reach/portal/join', [ReachPortalController::class, 'join']);
        Route::post('/reach/portal/widgets', [ReachPortalController::class, 'addWidget']);
        Route::post('/reach/portal/settings', [ReachPortalController::class, 'settings']);
        Route::post('/reach/portal/wallet/{action}', [ReachPortalController::class, 'wallet'])->where('action', 'early-cashout|withdraw|ad-credit');
    });
    // R-3: free AI crawler checker (config seo_tools.module_gated): noindex, never in the sitemap, menus or /tools hub.
    Route::get('/tools/'.ReachToolController::SLUG, [ReachToolController::class, 'show'])->defaults('lang', 'ar');
    Route::get('/en/tools/'.ReachToolController::SLUG, [ReachToolController::class, 'show'])->defaults('lang', 'en');
    Route::post('/tools/'.ReachToolController::SLUG.'/run', [ReachToolController::class, 'run'])->middleware(['throttle:30,1', ToolAbuseGuard::class]);
});
// XS-13 / XS-23 (R4, D-306): no-login lead page for a client's front desk (module lead_desk).
Route::get('/l/{token}', [LeadDeskController::class, 'show'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{40}');
Route::post('/l/{token}', [LeadDeskController::class, 'store'])->middleware('throttle:30,1')->where('token', '[A-Za-z0-9]{40}');
Route::post('/l/{token}/{lead}', [LeadDeskController::class, 'update'])->middleware('throttle:60,1')->where(['token' => '[A-Za-z0-9]{40}', 'lead' => '[0-9]+']);
Route::get('/r/{token}', [ClientReportController::class, 'show'])->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{40}');
// #57 monthly PDF behind the same link
Route::post('/r/{token}/approvals/{approval}', [ClientReportController::class, 'answer'])->middleware('throttle:20,1')->where(['token' => '[A-Za-z0-9]{40}', 'approval' => '[0-9]+']);
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
Route::get('/magic-login/{token}', [MagicLoginController::class, 'show'])->middleware('throttle:20,1')->name('magic.login.show');
Route::post('/magic-login/{token}', [MagicLoginController::class, 'consume'])->middleware('throttle:10,1')->name('magic.login.consume');
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

// Wave B.2 (#195 & #202): First-party CAPI collector relay & post-purchase HDYHAU micro-survey (404 while intelligence_hub is off)
Route::get('/collect/{token}', [PublicAttributionCollectorController::class, 'status'])
    ->middleware('throttle:120,1')->where('token', '[A-Za-z0-9]{32,64}')->name('public.collect.status');
Route::post('/collect/{token}', [PublicAttributionCollectorController::class, 'collect'])
    ->middleware('throttle:120,1')->where('token', '[A-Za-z0-9]{32,64}')->name('public.collect.ingest');
Route::get('/survey/{token}', [PublicAttributionCollectorController::class, 'showSurvey'])
    ->middleware('throttle:60,1')->where('token', '[A-Za-z0-9]{32,64}')->name('public.survey.show');
Route::post('/survey/{token}', [PublicAttributionCollectorController::class, 'submitSurvey'])
    ->middleware('throttle:30,1')->where('token', '[A-Za-z0-9]{32,64}')->name('public.survey.submit');

// Wave 21 public growth pages (E2 `7c3df2e`) held back 2026-11-07 (D-168): the partner directory (view kept as
// public/partners-held; /partners is now the P-25 design-partner page, D-219), case studies and
// the uplift calculator showed invented agencies, results and an 18% figure. Views kept for a rebuild from real data.
foreach (['calculator', 'resources'] as $held) {
    Route::redirect('/'.$held, '/waitlist', 302);
    Route::redirect('/en/'.$held, '/en/waitlist', 302);
}
