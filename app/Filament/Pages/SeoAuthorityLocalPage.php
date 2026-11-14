<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Models\SeoBacklinkRecord;
use App\Models\SeoDisavowList;
use App\Models\SeoLocalLocation;
use App\Models\SeoTopicCluster;
use App\Models\SeoTrackedKeyword;
use App\Support\CurrentClient;
use App\Support\Seo\SeoBacklinkAndDisavow;
use App\Support\Seo\SeoLocalGbpManager;
use App\Support\Seo\SeoRankTracker;
use App\Support\Seo\SeoTopicalClusterPlanner;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * E2-070 (module seo_audit, off): Rank tracker & cannibalization detector, topical silos,
 * backlink CSV + competitor gap + Google Disavow .txt export, and multi-branch Local GBP manager.
 */
class SeoAuthorityLocalPage extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'seo_audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Ranks, authority & local SEO';

    protected static ?string $title = 'Ranks, authority & local SEO';

    protected static ?string $slug = 'seo-authority-local';

    protected static ?int $navigationSort = 33;

    protected string $view = 'filament.pages.seo-authority-local';

    public ?int $clientId = null;

    // Rank tracker inputs
    public string $newKeyword = '';

    public string $newLocale = 'ar';

    public string $newLocationCode = 'SA';

    public string $newDevice = 'mobile';

    // Topical cluster inputs
    public string $pillarTitle = '';

    public string $pillarUrl = '';

    public string $spokesLines = '';

    public string $anchorLines = '';

    public string $briefNotes = '';

    // Backlinks & disavow inputs
    public string $backlinksCsv = '';

    // Local branch inputs
    public string $branchNameAr = '';

    public string $branchNameEn = '';

    public string $branchPhone = '';

    public string $branchAddressAr = '';

    public string $branchAddressEn = '';

    public string $branchWeekdayHours = '09:00-22:00';

    public static function getNavigationLabel(): string
    {
        return __('Ranks, authority & local SEO');
    }

    public function getTitle(): string
    {
        return __('Ranks, authority & local SEO');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('SEO');
    }

    public function mount(): void
    {
        $this->clientId = $this->client()?->id;
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

    public function addTrackedKeyword(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        if (trim($this->newKeyword) === '') {
            return;
        }
        SeoRankTracker::trackKeyword(
            $client,
            $this->newKeyword,
            $this->newLocale,
            $this->newLocationCode,
            $this->newDevice
        );
        $this->newKeyword = '';
        Notification::make()->title(__('Keyword added to tracker.'))->success()->send();
    }

    public function syncRanksFromGsc(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        $n = SeoRankTracker::syncSnapshotsFromGsc($client);
        Notification::make()->title(__('Rank snapshots updated from Search Console: :count', ['count' => $n]))->success()->send();
    }

    public function saveTopicCluster(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        if (trim($this->pillarTitle) === '' || trim($this->pillarUrl) === '') {
            return;
        }
        $spokes = [];
        foreach (preg_split('/\R/u', trim($this->spokesLines)) ?: [] as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (($parts[0] ?? '') !== '') {
                $spokes[] = [
                    'title' => $parts[0],
                    'url' => $parts[1] ?? '',
                    'locale' => $parts[2] ?? 'ar',
                    'status' => $parts[3] ?? 'planned',
                ];
            }
        }
        $anchors = array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->anchorLines) ?: [])));

        SeoTopicalClusterPlanner::saveCluster(
            $client,
            $this->pillarTitle,
            $this->pillarUrl,
            $spokes,
            $anchors,
            $this->briefNotes ?: null
        );
        $this->reset(['pillarTitle', 'pillarUrl', 'spokesLines', 'anchorLines', 'briefNotes']);
        Notification::make()->title(__('Topical cluster saved.'))->success()->send();
    }

    public function importBacklinksCsv(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        if (trim($this->backlinksCsv) === '') {
            return;
        }
        $n = SeoBacklinkAndDisavow::importGscLinksCsv($client, $this->backlinksCsv);
        $this->backlinksCsv = '';
        Notification::make()->title(__('Backlink rows imported: :count', ['count' => $n]))->success()->send();
    }

    public function toggleDisavowFlag(int $recordId): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        $rec = SeoBacklinkRecord::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->find($recordId);
        if ($rec) {
            $rec->update(['is_flagged_disavow' => ! $rec->is_flagged_disavow]);
        }
    }

    public function downloadDisavowTxt(): ?StreamedResponse
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        $list = SeoBacklinkAndDisavow::exportDisavowTxt($client);

        return response()->streamDownload(
            fn () => print ($list->exported_txt),
            'disavow-client-'.$client->id.'.txt',
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    public function saveLocalBranch(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        if (trim($this->branchNameAr) === '' && trim($this->branchNameEn) === '') {
            return;
        }
        $hours = array_fill_keys(SeoLocalGbpManager::DAYS, trim($this->branchWeekdayHours));
        SeoLocalGbpManager::saveLocation($client, [
            'name_ar' => $this->branchNameAr,
            'name_en' => $this->branchNameEn,
            'phone_e164' => $this->branchPhone,
            'address_ar' => $this->branchAddressAr,
            'address_en' => $this->branchAddressEn,
            'hours_json' => $hours,
        ]);
        $this->reset(['branchNameAr', 'branchNameEn', 'branchPhone', 'branchAddressAr', 'branchAddressEn']);
        Notification::make()->title(__('Local branch saved and NAP checked.'))->success()->send();
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
            'keywords' => $client
                ? SeoTrackedKeyword::with(['snapshots' => fn ($q) => $q->latest('checked_at')->limit(1)])
                    ->where('agency_id', $client->agency_id)
                    ->where('client_id', $client->id)
                    ->orderBy('keyword')
                    ->get()
                : collect(),
            'cannibalization' => $client ? SeoRankTracker::detectCannibalization($client) : [],
            'clusters' => $client
                ? SeoTopicCluster::where('agency_id', $client->agency_id)->where('client_id', $client->id)->latest('id')->get()
                : collect(),
            'backlinks' => $client
                ? SeoBacklinkRecord::where('agency_id', $client->agency_id)->where('client_id', $client->id)->latest('id')->limit(30)->get()
                : collect(),
            'disavowLists' => $client
                ? SeoDisavowList::where('agency_id', $client->agency_id)->where('client_id', $client->id)->latest('generated_at')->limit(5)->get()
                : collect(),
            'locations' => $client
                ? SeoLocalLocation::where('agency_id', $client->agency_id)->where('client_id', $client->id)->latest('id')->get()
                : collect(),
        ];
    }
}
