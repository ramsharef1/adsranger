<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\Seo\ArabicKeywordEngine;
use App\Support\Seo\SafeFetcher;
use App\Support\Seo\SearchConsole;
use App\Support\Seo\SerpReadiness;
use App\Support\Seo\SiteAuditRunner;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

/**
 * Arabic SEO & Search Workspace (S1 Keyword Engine + S2 SERP Readiness + S3 Site Audit + GSC Analyzer).
 * Read-only analysis surface available across workspace roles.
 */
class ArabicSeoPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    /** E2-041 scaffold (sample Search Console rows, single-page audit): held with the SEO suite until the A1 EX check (D-249). */
    protected static string $module = 'seo_audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Arabic SEO';

    protected static ?string $title = 'Arabic SEO & search';

    protected static ?string $slug = 'arabic-seo';

    protected static ?int $navigationSort = 27;

    protected string $view = 'filament.pages.arabic-seo';

    public string $seedKeyword = 'إدارة حملات إعلانية';

    public string $serpTitle = 'منصة إدارة الإعلانات والسيو العربي للوكالات | AdsRanger';

    public string $serpDescription = 'راقب ميزانيات العملاء وحسّن ظهور صفحات الهبوط في محركات البحث العربية والإنجليزية من لوحة واحدة.';

    public string $auditUrl = '';

    /** @var array<string, mixed>|null */
    public ?array $auditResult = null;

    public function runSiteAudit(): void
    {
        $this->validate(['auditUrl' => ['required', 'string', 'max:500', 'regex:#^https?://#i']]);
        $key = 'panel-seo-audit:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('auditUrl', __('Too many checks. Try again in a minute.'));

            return;
        }
        RateLimiter::hit($key, 60);

        $fetched = SafeFetcher::fetch(trim($this->auditUrl));
        if (! ($fetched['ok'] ?? false) || ($fetched['status'] ?? 0) !== 200) {
            $this->addError('auditUrl', __('Could not open the page (HTTP :status).', ['status' => $fetched['status'] ?? 'error']));
            $this->auditResult = null;

            return;
        }

        $this->auditResult = SiteAuditRunner::auditBatch(
            [(string) $fetched['url'] => (string) ($fetched['html'] ?? '')],
            trim($this->seedKeyword) !== '' ? trim($this->seedKeyword) : null,
        );
    }

    protected function getViewData(): array
    {
        return [
            'kwCluster' => ArabicKeywordEngine::expand($this->seedKeyword),
            'serpCheck' => SerpReadiness::evaluateSnippet($this->serpTitle, $this->serpDescription, $this->seedKeyword),
            'gsc' => SearchConsole::analyze(),
            'audit' => $this->auditResult,
        ];
    }
}
