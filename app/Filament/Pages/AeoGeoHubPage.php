<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\FollowsCurrentClient;
use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AeoDomainAudit;
use App\Models\AeoTrackedPrompt;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\CurrentClient;
use App\Support\Seo\AeoGeoManager;
use App\Support\Seo\SearchConsole;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Wave B.1 (#181–#192): AEO & GEO AI Search Visibility & Organic SEO Depth Hub.
 * Gated behind `Modules::on('organic')` (D-229 global default `false`).
 */
class AeoGeoHubPage extends Page
{
    use FollowsCurrentClient;
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'organic';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'Create & plan';

    protected static ?string $navigationLabel = 'AEO & GEO search';

    protected static ?string $title = 'AEO & GEO AI search visibility';

    protected static ?string $slug = 'aeo-geo-hub';

    protected static ?int $navigationSort = 37;

    protected string $view = 'filament.pages.aeo-geo-hub';

    public string $promptText = '';

    public string $promptLocale = 'ar';

    public string $funnelStage = 'consideration';

    public string $competitorsCsv = '';

    public string $auditDomainUrl = '';

    public string $highConvertingUrlsText = '';

    public string $fanOutSeed = '';

    public array $fanOutResults = [];

    public string $snippetTargetUrl = '';

    public string $snippetQuestion = '';

    public array $snippetPreview = [];

    public string $schemaType = 'Organization';

    public string $schemaSameAsText = '';

    public string $schemaPreviewJson = '';

    public string $briefCompetitor = '';

    public string $briefCity = 'Riyadh';

    public string $briefTemplate = 'vs';

    public array $briefPreview = [];

    protected function resolveScopedClient(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user) {
            return null;
        }

        $current = CurrentClient::resolve();
        if ($current && Client::visibleTo($user, $agency)->whereKey($current->id)->exists()) {
            return $current;
        }

        return Client::visibleTo($user, $agency)->orderBy('name')->first();
    }

    public function addTrackedPrompt(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $client = $this->resolveScopedClient();
        abort_unless($agency && $client && TeamRoles::canEdit($user, $agency), 403);

        $this->validate([
            'promptText' => ['required', 'string', 'min:4', 'max:500'],
            'promptLocale' => ['required', Rule::in(['ar', 'en'])],
            'funnelStage' => ['required', Rule::in(['awareness', 'consideration', 'decision', 'comparison'])],
            'competitorsCsv' => ['nullable', 'string', 'max:400'],
        ]);

        $competitors = array_slice(
            array_values(array_filter(array_map('trim', explode(',', $this->competitorsCsv)))),
            0,
            5
        );

        $prompt = AeoTrackedPrompt::query()->create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'prompt_text' => trim($this->promptText),
            'locale' => $this->promptLocale,
            'funnel_stage' => $this->funnelStage,
            'competitors_json' => $competitors,
            'is_active' => true,
        ]);

        AuditLog::record('aeo.prompt_created', $agency, ['client_id' => $client->id, 'prompt_id' => $prompt->id]);
        $this->promptText = '';
        Notification::make()->title(__('Tracked prompt saved'))->success()->send();
    }

    public function runCitationTracker(?int $promptId = null): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $client = $this->resolveScopedClient();
        abort_unless($agency && $client && TeamRoles::canEdit($user, $agency), 403);

        $query = AeoTrackedPrompt::query()
            ->where('agency_id', $agency->id)
            ->where('client_id', $client->id)
            ->where('is_active', true);

        if ($promptId !== null) {
            $query->whereKey($promptId);
        }

        $prompts = $query->limit(10)->get();
        $runsCount = 0;
        foreach ($prompts as $p) {
            $runsCount += count(AeoGeoManager::runCitationCheck($p));
        }

        AuditLog::record('aeo.citation_runs_executed', $agency, ['client_id' => $client->id, 'runs' => $runsCount]);
        Notification::make()->title(__('Citation check completed'))->success()->send();
    }

    public function deleteTrackedPrompt(int $promptId): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $client = $this->resolveScopedClient();
        abort_unless($agency && $client && TeamRoles::canEdit($user, $agency), 403);

        AeoTrackedPrompt::query()
            ->where('agency_id', $agency->id)
            ->where('client_id', $client->id)
            ->whereKey($promptId)
            ->delete();

        Notification::make()->title(__('Tracked prompt removed'))->success()->send();
    }

    public function runDomainAeoAudit(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $client = $this->resolveScopedClient();
        abort_unless($agency && $client && TeamRoles::canEdit($user, $agency), 403);

        $this->validate([
            'auditDomainUrl' => ['required', 'string', 'max:500', 'regex:#^https?://#i'],
        ]);

        $hcUrls = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->highConvertingUrlsText) ?: [])));
        $audit = AeoGeoManager::runAndSaveDomainAudit($client, $this->auditDomainUrl, $hcUrls);

        if (! $audit) {
            Notification::make()->title(__('Domain check blocked or unreachable'))->danger()->send();

            return;
        }

        AuditLog::record('aeo.domain_audit_saved', $agency, ['client_id' => $client->id, 'audit_id' => $audit->id]);
        Notification::make()->title(__('Domain AEO & GEO audit saved'))->success()->send();
    }

    /** Read-only view method (#186). */
    public function previewFanOut(): void
    {
        if (trim($this->fanOutSeed) === '') {
            return;
        }
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $this->fanOutResults = AeoGeoManager::generateQueryFanOut($this->fanOutSeed, $locale);
    }

    /** Read-only view method (#189). */
    public function previewSnippetRewrite(): void
    {
        if (! preg_match('#^https?://#i', trim($this->snippetTargetUrl))) {
            return;
        }
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $this->snippetPreview = AeoGeoManager::rewriteAnswerFirstSnippet($this->snippetTargetUrl, $this->snippetQuestion ?: 'Overview', $locale);
    }

    /** Read-only view method (#188). */
    public function previewEntitySchema(): void
    {
        $client = $this->resolveScopedClient();
        if (! $client) {
            return;
        }
        $sameAs = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->schemaSameAsText) ?: [])));
        $res = AeoGeoManager::buildEntitySchema($this->schemaType, [
            'name' => $client->name,
            'url' => $this->auditDomainUrl ?: 'https://example.com',
            'description' => $client->name,
            'same_as' => $sameAs,
            'city' => $this->briefCity,
            'faqs' => [['q' => $client->name.'?', 'a' => $client->name]],
        ]);
        $this->schemaPreviewJson = $res['json_ld'];
    }

    /** Read-only view method (#192). */
    public function previewComparisonBrief(): void
    {
        $client = $this->resolveScopedClient();
        if (! $client || trim($this->briefCompetitor) === '') {
            return;
        }
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $this->briefPreview = AeoGeoManager::buildComparisonBrief(
            $client->name,
            $this->briefCompetitor,
            $this->briefCity,
            $locale,
            $this->briefTemplate
        );
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $client = $this->resolveScopedClient();

        if (! $agency || ! $client) {
            return [
                'client' => null,
                'canEdit' => false,
                'prompts' => collect(),
                'matrix' => null,
                'referrals' => null,
                'citationGaps' => [],
                'latestAudit' => null,
                'cannibalization' => ['cannibalized' => [], 'decaying_pages' => []],
            ];
        }

        $prompts = AeoTrackedPrompt::query()
            ->with(['citationRuns' => fn ($q) => $q->latest('checked_at')->limit(5)])
            ->where('agency_id', $agency->id)
            ->where('client_id', $client->id)
            ->latest()
            ->get();

        $latestAudit = AeoDomainAudit::query()
            ->where('agency_id', $agency->id)
            ->where('client_id', $client->id)
            ->latest('audited_at')
            ->first();

        $gscSample = array_map(fn (array $r): array => $r + ['url' => 'https://example.com/'], SearchConsole::sampleRows());

        return [
            'client' => $client,
            'canEdit' => TeamRoles::canEdit($user, $agency),
            'prompts' => $prompts,
            'matrix' => AeoGeoManager::shareOfModelMatrix($client),
            'referrals' => AeoGeoManager::llmReferralSlice($client),
            'citationGaps' => AeoGeoManager::findCitationGaps($client, $latestAudit?->domain_url),
            'latestAudit' => $latestAudit,
            'cannibalization' => AeoGeoManager::detectCannibalizationAndDecay($gscSample),
        ];
    }
}
