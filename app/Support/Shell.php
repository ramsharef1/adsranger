<?php

namespace App\Support;

use App\Filament\Pages;
use App\Filament\Resources;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource;
use Throwable;

/**
 * PX-01 / PX-02 (D-302, UI/UX shell v1 + Today v2 boards): the new panel shell, behind the `panel_shell` module switch.
 * The header switches area (Today · Clients · Ads · SEO · AI · Reports); the sidebar lists only that area's pages, plus
 * every page that belongs to no area (settings, team), so nothing becomes unreachable. Phones get a bottom bar.
 */
class Shell
{
    public const AREAS = ['today', 'clients', 'ads', 'seo', 'ai', 'reports'];

    /** page or resource class → area. Pages not listed here show in every area. */
    public const MAP = [
        Pages\DailyDesk::class => 'today',
        Resources\Alerts\AlertResource::class => 'today',
        Pages\PacingBoard::class => 'today',
        Pages\MenaCalendarPage::class => 'today',
        Pages\ClientCalendarPage::class => 'today',
        Pages\RamadanReadinessPage::class => 'today',
        Pages\MarketDatesPage::class => 'today',

        Resources\Clients\ClientResource::class => 'clients',
        Pages\CompareClients::class => 'clients',
        Pages\MeetingPrepPage::class => 'clients',
        Pages\AccountAuditPage::class => 'clients',
        Resources\DataImports\DataImportResource::class => 'clients',
        Pages\ImportReviewPage::class => 'clients',

        Pages\TopAdsPage::class => 'ads',
        Resources\AdAccounts\AdAccountResource::class => 'ads',
        Resources\Connections\ConnectionResource::class => 'ads',
        Pages\Networks::class => 'ads',
        Pages\NetworkCatalogPage::class => 'ads',
        Pages\GoogleAdsPage::class => 'ads',
        Pages\BudgetPlanner::class => 'ads',
        Pages\GoalCalculatorPage::class => 'ads',
        Pages\BenchmarksPage::class => 'ads',
        Pages\CompetitorsPage::class => 'ads',
        Pages\InsightsPage::class => 'ads',
        Pages\LandingPagesPage::class => 'ads',
        Pages\UtmBuilderPage::class => 'ads',
        Pages\ArabicPhrasesPage::class => 'ads',
        Pages\LaunchCheckPage::class => 'ads',
        Pages\StudioPage::class => 'ads',
        Pages\AgencyOpsHubPage::class => 'ads',
        Pages\OrganicSocialPage::class => 'ads',

        Pages\SeoAuditPage::class => 'seo',
        Pages\SeoTelemetryPage::class => 'seo',
        Pages\SeoAuthorityLocalPage::class => 'seo',
        Pages\ArabicSeoPage::class => 'seo',
        Pages\AeoGeoHubPage::class => 'seo',

        Pages\ClientBrainPage::class => 'ai',
        Pages\NightShiftPage::class => 'ai',
        Pages\AiKeysPage::class => 'ai',
        Pages\AiConnectionsPage::class => 'ai',
        Pages\AiTransparencyPage::class => 'ai',
        Pages\FreeLiteView::class => 'ai',
        Pages\IntelligenceHubPage::class => 'ai',

        Pages\ExportData::class => 'reports',
        Pages\ProfitAttributionHubPage::class => 'reports',
        Pages\WebhooksPage::class => 'reports',
        Pages\ClientOnboardingLinksPage::class => 'reports',
        Pages\IntakeFormPage::class => 'clients',
    ];

    public static function on(?Agency $agency = null): bool
    {
        $agency ??= Filament::getTenant();

        return $agency instanceof Agency && Filament::getCurrentPanel()?->getId() === 'app' && Modules::on('panel_shell', null, $agency);
    }

    /** @return array<string, string> area → label */
    public static function labels(): array
    {
        return ['today' => __('Today'), 'clients' => __('Clients'), 'ads' => __('Ads'), 'seo' => __('SEO'), 'ai' => __('AI'), 'reports' => __('Reports')];
    }

    public static function areaOf(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }
        if (is_subclass_of($class, ResourcePage::class)) {
            $class = $class::getResource();
        }

        return self::MAP[$class] ?? null;
    }

    /** The area of the page being shown; kept in the session so Livewire requests and unmapped pages keep it. */
    public static function current(): string
    {
        $uses = request()->route()?->getAction('uses');
        $class = is_string($uses) ? explode('@', $uses)[0] : null;
        $area = static::areaOf($class);
        if ($area !== null) {
            session(['shell.area' => $area]);

            return $area;
        }

        return in_array(session('shell.area'), self::AREAS, true) ? session('shell.area') : 'today';
    }

    /** @var array<string, string>|null url path → area */
    protected static ?array $urls = null;

    /** @return array<string, string> */
    protected static function urls(): array
    {
        if (static::$urls !== null) {
            return static::$urls;
        }
        $out = [];
        foreach (self::MAP as $class => $area) {
            try {
                $url = is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl('index') : $class::getUrl();
                $out[static::path($url)] = $area;
            } catch (Throwable) {
                // A page that can't build a URL here (module off, no tenant) simply has no area.
            }
        }

        return static::$urls = $out;
    }

    public static function forget(): void
    {
        static::$urls = null;
        app()->forgetInstance(NavigationManager::class);
    }

    protected static function path(?string $url): string
    {
        return rtrim((string) parse_url((string) $url, PHP_URL_PATH), '/');
    }

    public static function itemArea(NavigationItem $item): ?string
    {
        return static::urls()[static::path($item->getUrl())] ?? null;
    }

    /**
     * @param  array<NavigationGroup>  $groups
     * @return array<NavigationGroup>
     */
    public static function filter(array $groups, string $area): array
    {
        $out = [];
        foreach ($groups as $group) {
            $items = collect($group->getItems())->filter(fn (NavigationItem $i) => in_array(static::itemArea($i), [null, $area], true))->values();
            if ($items->isNotEmpty()) {
                $out[] = (clone $group)->items($items->all());
            }
        }

        return $out;
    }

    /** @return array<string, string> area → URL of its first page this person can open (areas with none are left out) */
    public static function areaLinks(): array
    {
        $links = [];
        foreach (ShellNavigationManager::allGroups() as $group) {
            foreach ($group->getItems() as $item) {
                $a = static::itemArea($item);
                if ($a !== null && ! isset($links[$a])) {
                    $links[$a] = (string) $item->getUrl();
                }
            }
        }

        return array_filter(array_replace(array_fill_keys(self::AREAS, null), $links));
    }

    /**
     * PX-03: the season ribbon: today in both calendars, the next 2 seasons, and one status sentence from open alerts
     * (D-88 levels) for the clients this person sees.
     *
     * @return array{date: string, hijri: string, next: list<array{label: string, days: int}>, clients: int, calm: int, critical: int, warning: int}
     */
    public static function ribbon(Agency $agency): array
    {
        $tz = $agency->timezone ?: 'UTC';
        $today = now($tz)->toDateString();
        $ar = app()->getLocale() === 'ar';
        $next = collect(MenaCalendar::events($today, now($tz)->addDays(90)->toDateString()))
            ->filter(fn ($e) => $e['start'] > $today)->sortBy('start')->unique('key')->take(2)
            ->map(fn ($e) => ['label' => $ar ? $e['ar'] : $e['label'], 'days' => (int) now($tz)->startOfDay()->diffInDays($e['start'])])->values()->all();

        $ids = Client::visibleTo(auth()->user(), $agency)->where('status', 'active')->pluck('id');
        $open = Alert::where('agency_id', $agency->id)->where('status', 'open')->whereIn('client_id', $ids)
            ->selectRaw('severity, count(*) as n, count(distinct client_id) as c')->groupBy('severity')->get()->keyBy('severity');
        $withAlerts = Alert::where('agency_id', $agency->id)->where('status', 'open')->whereIn('client_id', $ids)->whereIn('severity', ['critical', 'warning', 'high', 'medium'])->distinct()->count('client_id');

        return [
            'date' => now($tz)->locale(app()->getLocale())->translatedFormat($ar ? 'l j F Y' : 'l j M Y'),
            'hijri' => MenaCalendar::hijriLabel($today, $ar ? 'ar' : 'en'),
            'next' => $next,
            'clients' => $ids->count(), 'calm' => $ids->count() - $withAlerts,
            'critical' => (int) (($open['critical']->n ?? 0) + ($open['high']->n ?? 0)), 'warning' => (int) (($open['warning']->n ?? 0) + ($open['medium']->n ?? 0)),
        ];
    }

    /** PX-05: the clients for the header switcher, each with its worst open alert level (critical · warning · ok). */
    public static function clientsWithStatus(Agency $agency): array
    {
        $clients = Client::visibleTo(auth()->user(), $agency)->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $worst = Alert::where('agency_id', $agency->id)->where('status', 'open')->whereIn('client_id', $clients->pluck('id'))
            ->selectRaw("client_id, max(case when severity in ('critical', 'high') then 2 when severity in ('warning', 'medium') then 1 else 0 end) as w")->groupBy('client_id')->pluck('w', 'client_id');

        return $clients->map(fn (Client $c) => ['id' => $c->id, 'name' => $c->name, 'level' => [2 => 'critical', 1 => 'warning'][(int) ($worst[$c->id] ?? 0)] ?? 'ok'])->all();
    }

    /** PX-04: comfortable (44 px rows, default) or compact (desktop only). */
    public static function density(): string
    {
        return (auth()->user()?->ui_prefs['density'] ?? 'comfortable') === 'compact' ? 'compact' : 'comfortable';
    }
}
