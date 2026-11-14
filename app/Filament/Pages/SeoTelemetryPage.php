<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Models\ClientSearchConsoleRow;
use App\Models\SeoLogAnalysis;
use App\Support\CurrentClient;
use App\Support\Seo\Ga4FunnelReconciler;
use App\Support\Seo\GscSearchAnalyticsSync;
use App\Support\Seo\ServerAccessLogAnalyzer;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * E2-069 (module seo_audit, off): Search Console sync/CSV + striking distance + query decay,
 * GA4 3-way funnel reconciliation, and streamed server access log analyzer.
 */
class SeoTelemetryPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;
    use WithFileUploads;

    protected static string $module = 'seo_audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Search & log telemetry';

    protected static ?string $title = 'Search & log telemetry';

    protected static ?string $slug = 'seo-telemetry';

    protected static ?int $navigationSort = 32;

    protected string $view = 'filament.pages.seo-telemetry';

    public ?int $clientId = null;

    public string $siteUrl = '';

    public string $gscAccessToken = '';

    public string $gscCsv = '';

    public string $gscStatus = '';

    public string $ga4PropertyId = '';

    public string $ga4AccessToken = '';

    public string $ga4Status = '';

    public string $startDate = '';

    public string $endDate = '';

    /** @var TemporaryUploadedFile|null */
    public $logUpload = null;

    public string $logPaste = '';

    public string $logFilename = 'access.log';

    public static function getNavigationLabel(): string
    {
        return __('Search & log telemetry');
    }

    public function getTitle(): string
    {
        return __('Search & log telemetry');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('SEO');
    }

    public function mount(): void
    {
        $client = $this->client();
        $this->clientId = $client?->id;
        $this->endDate = now()->toDateString();
        $this->startDate = now()->subDays(27)->toDateString();
        if ($client) {
            $existingSite = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
                ->where('client_id', $client->id)
                ->value('site_url');
            $this->siteUrl = (string) ($existingSite ?: '');
        }
    }

    protected function client(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user) {
            return null;
        }
        $q = Client::visibleTo($user, $agency);
        if ($this->clientId) {
            return (clone $q)->whereKey($this->clientId)->first();
        }
        $current = CurrentClient::resolve($agency);

        return ($current ? (clone $q)->whereKey($current->id)->first() : null) ?? $q->orderBy('name')->first();
    }

    protected function canManage(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    public function syncGscApi(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }

        $res = GscSearchAnalyticsSync::syncApi(
            $client,
            $this->siteUrl,
            $this->gscAccessToken,
            $this->startDate,
            $this->endDate
        );

        $this->gscStatus = $res['status'];
        if ($res['status'] === 'unconfigured') {
            Notification::make()->title(__('Search Console credentials are not configured yet (queued_until_credentials).'))->warning()->send();

            return;
        }
        if (! $res['ok']) {
            Notification::make()->title(__('Search Console sync did not return rows.'))->warning()->send();

            return;
        }
        $this->gscAccessToken = '';
        Notification::make()->title(__('Search Console rows saved.'))->success()->send();
    }

    public function importGscCsv(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        if (trim($this->gscCsv) === '') {
            return;
        }
        $saved = GscSearchAnalyticsSync::importCsv(
            $client,
            $this->siteUrl !== '' ? $this->siteUrl : 'https://example.com',
            $this->gscCsv,
            $this->endDate
        );
        $this->gscCsv = '';
        $this->gscStatus = $saved > 0 ? 'csv_imported' : 'empty';
        Notification::make()->title(__('CSV rows imported: :count', ['count' => $saved]))->success()->send();
    }

    public function syncGa4Api(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }

        $res = Ga4FunnelReconciler::syncApi(
            $client,
            $this->ga4PropertyId,
            $this->ga4AccessToken,
            $this->startDate,
            $this->endDate
        );

        $this->ga4Status = $res['status'];
        if ($res['status'] === 'unconfigured') {
            Notification::make()->title(__('GA4 credentials are not configured yet (queued_until_credentials).'))->warning()->send();

            return;
        }
        if (! $res['ok']) {
            Notification::make()->title(__('GA4 sync did not return rows.'))->warning()->send();

            return;
        }
        $this->ga4AccessToken = '';
        Notification::make()->title(__('GA4 funnel rows saved.'))->success()->send();
    }

    public function analyzeAccessLog(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }

        if ($this->logUpload instanceof TemporaryUploadedFile) {
            ServerAccessLogAnalyzer::analyzeFile(
                $client,
                $this->logUpload->getRealPath(),
                $this->logUpload->getClientOriginalName()
            );
            $this->logUpload = null;
            Notification::make()->title(__('Access log analyzed.'))->success()->send();

            return;
        }

        if (trim($this->logPaste) !== '') {
            ServerAccessLogAnalyzer::analyzeContent(
                $client,
                $this->logPaste,
                $this->logFilename !== '' ? $this->logFilename : 'access.log'
            );
            $this->logPaste = '';
            Notification::make()->title(__('Access log analyzed.'))->success()->send();
        }
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $client = $this->client();

        return [
            'ar' => app()->getLocale() === 'ar',
            'clients' => $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect(),
            'client' => $client,
            'canManage' => $this->canManage(),
            'striking' => $client ? GscSearchAnalyticsSync::strikingDistance($client) : [],
            'decay' => $client ? GscSearchAnalyticsSync::queryDecay($client) : [],
            'reconciliation' => $client ? Ga4FunnelReconciler::reconcile($client, $this->startDate, $this->endDate) : null,
            'latestLog' => $client
                ? SeoLogAnalysis::where('agency_id', $client->agency_id)->where('client_id', $client->id)->latest('analyzed_at')->first()
                : null,
        ];
    }
}
