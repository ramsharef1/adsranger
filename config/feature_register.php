<?php

use App\Audit\AccountAudit;
use App\Connectors\ConnectorRegistry;
use App\Connectors\RedditConnector;
use App\Filament\Admin\Pages\CostsAndLaunch;
use App\Filament\Admin\Pages\LiveVerificationBoard;
use App\Filament\Admin\Pages\PlatformSettings;
use App\Filament\Admin\Pages\ReachControlBoard;
use App\Filament\Admin\Pages\RegisterGuardBoard;
use App\Filament\Admin\Pages\ToolGuardBoard;
use App\Filament\Pages\AccountAuditPage;
use App\Filament\Pages\AeoCitationRadarPage;
use App\Filament\Pages\AiConnectionsPage;
use App\Filament\Pages\AiKeysPage;
use App\Filament\Pages\ClientBrainPage;
use App\Filament\Pages\CreativeFatigueBoardPage;
use App\Filament\Pages\FreeLiteView;
use App\Filament\Pages\MyDevices;
use App\Filament\Pages\NightShiftPage;
use App\Filament\Pages\OnboardingPage;
use App\Filament\Pages\RetainerSplitPage;
use App\Filament\Pages\SeoAuditPage;
use App\Filament\Pages\SeoAuthorityLocalPage;
use App\Filament\Pages\SeoTelemetryPage;
use App\Filament\Pages\WhatsNewPage;
use App\Filament\Pages\WorkspaceActivity;
use App\Http\Controllers\ReachPortalController;
use App\Http\Controllers\ReachServeController;
use App\Http\Controllers\ReachToolController;
use App\Http\Controllers\StoreWebhookController;
use App\Http\Middleware\AiConnectorGuard;
use App\Http\Middleware\FreePlanFence;
use App\Http\Middleware\ToolAbuseGuard;
use App\Mcp\Servers\AdsRangerServer;
use App\Models\CostLine;
use App\Models\LaunchCheck;
use App\Support\AdminShell;
use App\Support\AgencyOps\RetainerSplit;
use App\Support\AiWorkspace\AiBox;
use App\Support\AiWorkspace\Brain;
use App\Support\AiWorkspace\FreePlan;
use App\Support\AiWorkspace\NightShift;
use App\Support\AiWorkspace\QualityGate;
use App\Support\Arabic\Normaliser;
use App\Support\Attribution\StoreWebhooks;
use App\Support\CriticalPush;
use App\Support\Devices;
use App\Support\LiveVerification;
use App\Support\Modules;
use App\Support\PlatformHealth;
use App\Support\PoweredBy;
use App\Support\PublicSite;
use App\Support\Reach\Attribution;
use App\Support\Reach\OfferImporter;
use App\Support\Reach\Postbacks;
use App\Support\Reach\PublisherKit;
use App\Support\Reach\Serve;
use App\Support\Reach\Wallet;
use App\Support\RestoreTest;
/*
 * A-12 (FR-029) register-to-code guard. Every row marked Done or Live in ADSR-060 / ADSR-920 is listed here with the
 * code it lives in and the test that proves it. RegisterToCodeTest fails if a listed class, view or test is missing,
 * so a row can't be called Done without code + a test. E1 adds the row in the same commit as the build.
 *
 * 'code' entries: a class name, "view:<name>", "route:<uri>" or "config:<file>". 'tests': files under tests/.
 */
use App\Support\Seo\AeoCitationRadar;
use App\Support\Seo\AiCrawlerChecker;
use App\Support\Seo\Audit\PageSpeed;
use App\Support\Seo\Audit\SiteAuditor;
use App\Support\Seo\Ga4FunnelReconciler;
use App\Support\Seo\GscSearchAnalyticsSync;
use App\Support\Seo\PageAudit;
use App\Support\Seo\PublicCatalog;
use App\Support\Seo\SeoBacklinkAndDisavow;
use App\Support\Seo\SeoLocalGbpManager;
use App\Support\Seo\SeoRankTracker;
use App\Support\Seo\SeoTopicalClusterPlanner;
use App\Support\Seo\ServerAccessLogAnalyzer;
use App\Support\Seo\SiteAuditRunner;
use App\Support\Seo\ToolGuard;
use App\Support\Site\CopyLint;
use App\Support\Studio\CreativeFatigueDetector;
use App\Support\WebPush;

return [
    '#173' => ['title' => 'Day-1 account audit', 'code' => [AccountAudit::class, AccountAuditPage::class], 'tests' => ['Feature/AccountAuditTest.php']],
    'FR-042' => ['title' => 'Onboarding (steps 1–3 required)', 'code' => [OnboardingPage::class], 'tests' => ['Feature/OnboardingTest.php']],
    '#76' => ['title' => "What's new page", 'code' => [WhatsNewPage::class], 'tests' => ['Feature/WhatsNewTest.php']],
    '#175' => ['title' => 'Phone alerts for Critical alerts', 'code' => [CriticalPush::class, WebPush::class], 'tests' => ['Feature/CriticalPushTest.php']],
    'B-1' => ['title' => 'Arabic text layer + FR-095 test set', 'code' => [Normaliser::class], 'tests' => ['Feature/ArabicNormaliserTest.php']],
    'B-5' => ['title' => 'Site audit A1 (module seo_audit, off)', 'code' => [SeoAuditPage::class, SiteAuditRunner::class], 'tests' => ['Feature/SeoAuditA1Test.php']],
    'UX-253' => ['title' => 'Free page audit names its small deductions', 'code' => [PageAudit::class, 'route:tools/page-audit/run'], 'tests' => ['Feature/SiteBatch0Cl87Test.php', 'Feature/PageAuditTest.php']],
    'GB-01' => ['title' => 'Articles held behind SITE_ARTICLES_PUBLIC', 'code' => [PublicSite::class], 'tests' => ['Feature/SiteBatch0Cl87Test.php']],
    'A-3' => ['title' => 'Error + uptime alerts', 'code' => [PlatformHealth::class], 'tests' => ['Feature/PlatformHealthA3Test.php', 'Feature/ScheduleInProcessTest.php']],
    'A-4' => ['title' => '/admin cost page', 'code' => [CostsAndLaunch::class, CostLine::class], 'tests' => ['Feature/AdminCostsLaunchTest.php']],
    'A-5' => ['title' => 'Restore plan + monthly restore test', 'code' => [RestoreTest::class], 'tests' => ['Feature/RestoreTestA5Test.php']],
    'A-8' => ['title' => 'Launch readiness scorecard', 'code' => [LaunchCheck::class, CostsAndLaunch::class], 'tests' => ['Feature/AdminCostsLaunchTest.php']],
    'A-10' => ['title' => '/admin › Modules', 'code' => [Modules::class, PlatformSettings::class], 'tests' => ['Feature/AdminModulesA10Test.php']],
    'A-11' => ['title' => 'Live-verification board', 'code' => [LiveVerification::class, LiveVerificationBoard::class], 'tests' => ['Feature/LiveVerificationA11Test.php']],
    'A-12' => ['title' => 'Register-to-code guard', 'code' => ['config:feature_register'], 'tests' => ['Feature/RegisterToCodeTest.php']],
    'A-17' => ['title' => 'Signed-in devices + new-device alert', 'code' => [Devices::class, MyDevices::class], 'tests' => ['Feature/DevicesAndActivityA17A18Test.php']],
    'A-18' => ['title' => 'Owner view of the workspace audit log', 'code' => [WorkspaceActivity::class], 'tests' => ['Feature/DevicesAndActivityA17A18Test.php']],
    'BZ-10' => ['title' => 'Free tools abuse guard', 'code' => [ToolGuard::class, ToolAbuseGuard::class, ToolGuardBoard::class], 'tests' => ['Feature/ToolGuardBz10Test.php']],
    'GB-06' => ['title' => 'Public copy lint (Growth CR-01…CR-23) + AR/EN parity (GB-38)', 'code' => [CopyLint::class], 'tests' => ['Feature/PublicCopyLintTest.php']],
    // D-281: the Wave B.1 AEO / GEO scaffold is relabelled #301–#312 (module off, not Done, so no row yet); FR-087…095 keep #181–#189.
    'GB-01b' => ['title' => 'Held guides, posts and glossary terms; per-page release (D-279)', 'code' => [PublicSite::class, 'config:seo_tools'], 'tests' => ['Feature/ArticlesHeldD279Test.php']],
    'BX-05' => ['title' => '"Powered by AdsRanger" on client reports, opt-out (D-278 / D-280)', 'code' => [PoweredBy::class], 'tests' => ['Feature/PoweredByOptOutBx05Test.php', 'Feature/AgencyLogoTest.php']],
    'O-394' => ['title' => 'Copy rewrites 1–26 + glossary title template, no email promises (D-266)', 'code' => [PublicCatalog::class, CopyLint::class], 'tests' => ['Feature/PublicCopyLintTest.php']],
    'A-2' => ['title' => 'Recorded API replies as contract tests (Meta, Google Ads, TikTok, Reddit) + no-fake-success guard on 16 connectors; WR-06, WR-07', 'code' => [ConnectorRegistry::class, RedditConnector::class], 'tests' => ['Feature/ConnectorContractsA2Test.php']],
    'AIW-M1a' => ['title' => 'AI Workspace M1 core: quality gate (Q1, Q2, Q3, Q5, Q9), rule clean-up, output contracts, client brain + screen (module ai_workspace, off)', 'code' => [QualityGate::class, Brain::class, ClientBrainPage::class], 'tests' => ['Feature/AiWorkspaceM1CoreTest.php']],
    'AIW-M1b' => ['title' => 'AI Workspace M1: AdsRanger connector at mcp.adsranger.com (OAuth 2.1 + DCR, consent with ownership tick, 8 tools, 3 playbooks, guard, Connections screen)', 'code' => [AdsRangerServer::class, AiConnectorGuard::class, AiConnectionsPage::class], 'tests' => ['Feature/AiConnectorM1bTest.php']],
    'AIW-M1d' => ['title' => 'AI Workspace M1: own API keys with monthly cap (#159), AI box with 3 playbooks, night shift v0 (read + morning list)', 'code' => [AiKeysPage::class, AiBox::class, NightShift::class, NightShiftPage::class], 'tests' => ['Feature/AiWorkspaceM1dTest.php']],
    'AIW-M1c' => ['title' => 'AI Workspace M1: Free plan «الباقة المجانية» (lite view, fence, one Free per ad account, spend fence, idle sync pause), daily caps by plan, full AI Workspace from Pro (D-286)', 'code' => [FreePlan::class, FreePlanFence::class, FreeLiteView::class], 'tests' => ['Feature/AiWorkspaceM1cFreeTest.php']],
    'GB-11a' => ['title' => 'Glossary titles «ما هو / ما هي» by gender, ≤ 60 characters', 'code' => [PublicCatalog::class], 'tests' => ['Feature/GlossaryHeadingTest.php']],
    'BX-01' => ['title' => '/data hub: MENA ad calendar 2027 as CSV + JSON (CC BY 4.0) on our own domain, Dataset schema, drift test', 'code' => ['view:public.data', 'config:datasets'], 'tests' => ['Feature/OpenDataBx01Test.php']],
    'BX-03' => ['title' => '"Cite this" block + stable anchors on /data, /mena-calendar, glossary terms', 'code' => ['view:public.partials.cite'], 'tests' => ['Feature/OpenDataBx01Test.php']],
    'FR-REACH-R1' => ['title' => 'AdsRanger Reach R-1: schema, offer + coupon importer (AR/EN CSV), S2S postback receiver, 3-tier coupon attribution, dual estimate wallet, /admin Reach board (module reach, off; D-262 estimates)', 'code' => [OfferImporter::class, Postbacks::class, Attribution::class, Wallet::class, ReachControlBoard::class, 'route:reach/v1/postback/{token}'], 'tests' => ['Feature/ReachR1Test.php']],
    'FR-REACH-R2' => ['title' => 'AdsRanger Reach R-2: stateless /reach/v1/serve + offerwall + AI feed (geo, OS, URL keywords, payday / meal-time boost, UCB1, cached), < 5 KB web tag (no CLS, 0x0 guard, auto-skim), incent firewall, family-safe + 20-domain blocklist, human vs AI-agent counters, 4-tab /reach/portal, /reach landing (noindex, 404 while off)', 'code' => [Serve::class, ReachServeController::class, ReachPortalController::class, 'view:reach.portal', 'view:reach.landing', 'route:reach/v1/serve', 'route:reach/v1/ai-feed'], 'tests' => ['Feature/ReachR2Test.php']],
    'FR-REACH-R3' => ['title' => 'AdsRanger Reach R-3: free AI Bot Traffic & Unmonetized Revenue Checker (/tools/ai-crawler-revenue-checker): robots.txt per AI crawler, llms.txt + Offer markup, SafeFetcher + ToolAbuseGuard, noindex and out of sitemap / menus / hub / share cards (seo_tools.module_gated, module reach)', 'code' => [AiCrawlerChecker::class, ReachToolController::class, 'view:public.tools.ai-crawler-revenue-checker', 'config:seo_tools', 'route:tools/ai-crawler-revenue-checker/run'], 'tests' => ['Feature/ReachR3Test.php']],
    'FR-089-A2' => ['title' => 'Site audit A2 speed lab (E2-062): static asset checks SPEED-01..04 (render-blocking script / CSS, first image without dimensions, inline payload) + optional PageSpeed Insights v5 mobile (score, LCP, INP field-only, CLS, TTFB); speed chip only when speed_checked_at exists (UX-250); module seo_audit, off', 'code' => [PageSpeed::class, SiteAuditor::class, 'view:filament.pages.seo-audit'], 'tests' => ['Feature/SeoAuditA2SpeedTest.php']],
    'FR-STORE-SYNC' => ['title' => 'Salla / Zid / Shopify signed store webhooks (E2-063): /webhooks/stores/{token} (CSRF-exempt, hash_equals HMAC-SHA256, replay window + unique event_uid), finished orders → per-SKU snapshots + observed COD rates on client_unit_economics (#193), returned COD orders, stock updates → #197 bleed guard; store connections in the profit hub (managers only); module intelligence_hub, off', 'code' => [StoreWebhooks::class, StoreWebhookController::class, 'route:webhooks/stores/{token}', 'config:store_sync'], 'tests' => ['Feature/MenaStoreWebhooksTest.php']],
    'FR-REACH-R4' => ['title' => 'AdsRanger Reach R-4 (E2-064): network Sub-ID + postback presets (arabclicks / linkaraby aff_sub, admitad subid, impact subId1, direct_client subid) with per-endpoint overrides on /admin › Reach, and a deterministic WordPress plugin .zip + Cloudflare Worker snippet in /reach/portal › Placements (async tag.js, /llms.txt promo codes, schema.org Offer JSON-LD on pages that show offers); module reach, off', 'code' => [PublisherKit::class, Postbacks::class, ReachControlBoard::class, ReachPortalController::class, 'route:reach/portal/wordpress-plugin.zip'], 'tests' => ['Feature/ReachR4Test.php']],
    'FR-AEO-RADAR' => ['title' => 'AEO / GEO brand citation & AI readiness radar (E2-065): deterministic readiness out of 100 from SafeFetcher checks (robots.txt per AI crawler, /llms.txt, Organization / FAQPage / Product JSON-LD, AR / EN entity parity) + optional probes on the workspace\'s own AI key under its monthly cap (#159); no citation rate without probes; module seo_audit, off', 'code' => [AeoCitationRadar::class, AeoCitationRadarPage::class, 'view:filament.pages.aeo-citation-radar'], 'tests' => ['Feature/AeoGeoCitationRadarTest.php']],
    'FR-CREATIVE-FATIGUE' => ['title' => 'Creative fatigue & peak-season decay guard (E2-066): ad-level frequency saturation + click-through / ThruPlay rate drop vs the ad\'s own 3-week baseline, tighter limits during Ramadan\'s last ten nights, Eid and White Friday (MenaCalendar), open / resolved alerts, board with visibleTo + hide_spend and a draft-replacement link into Creative Studio; module studio, off', 'code' => [CreativeFatigueDetector::class, CreativeFatigueBoardPage::class, 'view:filament.pages.creative-fatigue-board', 'config:creative_fatigue'], 'tests' => ['Feature/CreativeFatigueGuardTest.php']],
    'FR-RETAINER-SPLIT' => ['title' => 'Retainer vs ad-spend split (E2-067): monthly pass-through ad spend vs agency management fee (fixed / share of spend / hybrid, minimum, tax on the fee only at the typed rate), ResultResolver::money (3 decimals JOD / KWD / BHD / OMR), CSV + ZATCA TLV Base64 + JoFotara UBL 2.1 XML drafts with a neutral reminder; no export for hide_spend clients; module agency_ops_hub, off', 'code' => [RetainerSplit::class, RetainerSplitPage::class, 'view:filament.pages.retainer-split'], 'tests' => ['Feature/AgencyRetainerSplitTest.php']],
    'FR-ADMIN-PX-SHELL' => ['title' => '/admin dense PX command-center shell (E2-068): 4-area switcher rail (Overview, Growth, Platform, Guards + All), ground-truth live telemetry ribbon (workspaces, modules + preview slugs, scheduler/queue heartbeat, Reach cache store, /app jump), compact/comfortable row density toggle, live Register-to-code guard viewer, and Slate/Teal cockpit polish', 'code' => [AdminShell::class, RegisterGuardBoard::class, 'view:panels.admin-shell-areas', 'view:panels.admin-shell-ribbon', 'view:panels.admin-shell-bottom'], 'tests' => ['Feature/AdminPxShellTest.php']],
    'FR-SEO-TELEMETRY' => ['title' => 'GSC Search Analytics sync/CSV + striking-distance (#4–#15) + query-decay detector, GA4 Data API v1 + 3-way reconciliation (Ad Claims vs GA4 Key Events vs Store Orders), and memory-safe streamed server access log analyzer (.log / .gz) under seo_audit (E2-069)', 'code' => [GscSearchAnalyticsSync::class, Ga4FunnelReconciler::class, ServerAccessLogAnalyzer::class, SeoTelemetryPage::class, 'view:filament.pages.seo-telemetry'], 'tests' => ['Feature/SeoGscGa4LogsTest.php']],
    'FR-SEO-AUTHORITY-LOCAL' => ['title' => 'Rank tracker + cannibalization detector, topical pillar-spoke silos, GSC backlink CSV + competitor domain intersection gap + Google Disavow .txt exporter, and multi-branch Local GBP NAP/RTL checker & bilingual review drafts under seo_audit (E2-070)', 'code' => [SeoRankTracker::class, SeoTopicalClusterPlanner::class, SeoBacklinkAndDisavow::class, SeoLocalGbpManager::class, SeoAuthorityLocalPage::class, 'view:filament.pages.seo-authority-local'], 'tests' => ['Feature/SeoAuthorityAndLocalTest.php']],
];
