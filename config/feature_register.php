<?php

use App\Audit\AccountAudit;
use App\Connectors\ConnectorRegistry;
use App\Connectors\RedditConnector;
use App\Filament\Admin\Pages\CostsAndLaunch;
use App\Filament\Admin\Pages\LiveVerificationBoard;
use App\Filament\Admin\Pages\PlatformSettings;
use App\Filament\Admin\Pages\ToolGuardBoard;
use App\Filament\Pages\AccountAuditPage;
use App\Filament\Pages\MyDevices;
use App\Filament\Pages\OnboardingPage;
use App\Filament\Pages\SeoAuditPage;
use App\Filament\Pages\WhatsNewPage;
use App\Filament\Pages\WorkspaceActivity;
use App\Http\Middleware\ToolAbuseGuard;
use App\Models\CostLine;
use App\Models\LaunchCheck;
use App\Support\Arabic\Normaliser;
use App\Support\CriticalPush;
use App\Support\Devices;
use App\Support\LiveVerification;
use App\Support\Modules;
use App\Support\PlatformHealth;
use App\Support\PoweredBy;
use App\Support\PublicSite;
use App\Support\RestoreTest;
use App\Support\Seo\PageAudit;
use App\Support\Seo\PublicCatalog;
use App\Support\Seo\SiteAuditRunner;
use App\Support\Seo\ToolGuard;
use App\Support\Site\CopyLint;
use App\Support\WebPush;

/*
 * A-12 (FR-029) register-to-code guard. Every row marked Done or Live in ADSR-060 / ADSR-920 is listed here with the
 * code it lives in and the test that proves it. RegisterToCodeTest fails if a listed class, view or test is missing,
 * so a row can't be called Done without code + a test. E1 adds the row in the same commit as the build.
 *
 * 'code' entries: a class name, "view:<name>", "route:<uri>" or "config:<file>". 'tests': files under tests/.
 */
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
];
