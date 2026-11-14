<?php

namespace App\Support;

use App\Filament\Admin\Pages;
use App\Filament\Admin\Resources;
use App\Models\Agency;
use App\Support\Ops\Heartbeat;
use App\Support\Ops\JobRuns;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Filament\Pages\Dashboard;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * E2-068 (PX-01 / PX-02 for /admin): dense multi-area command-center shell for super admins.
 * Organizes /admin across four areas (Overview · Growth · Platform · Guards) plus an "All" view,
 * pairs it with a ground-truth live telemetry ribbon, and honors ui_prefs['density'].
 */
class AdminShell
{
    public const AREAS = ['overview', 'growth', 'platform', 'guards'];

    /** Primary area per /admin page or resource class. Unmapped items remain visible in every area. */
    public const MAP = [
        Dashboard::class => 'overview',
        Pages\OperationsBoard::class => 'overview',
        Pages\LiveVerificationBoard::class => 'overview',
        Pages\AiScorecardBoard::class => 'overview',

        Resources\Agencies\AgencyResource::class => 'growth',
        Pages\CustomersBoard::class => 'growth',
        Resources\Plans\PlanResource::class => 'growth',
        Pages\WaitlistEntries::class => 'growth',
        Pages\PartnerApplications::class => 'growth',
        Pages\GrowthLog::class => 'growth',
        Pages\ReachControlBoard::class => 'growth',

        Pages\PlatformSettings::class => 'platform',
        Pages\CostsAndLaunch::class => 'platform',
        Resources\AdNetworks\AdNetworkResource::class => 'platform',
        Pages\NetworkRequests::class => 'platform',
        Pages\ReindexGateBoard::class => 'platform',
        Pages\LinksBoard::class => 'platform',
        Pages\HolidayStatusBoard::class => 'platform',
        Resources\Releases\ReleaseResource::class => 'platform',

        Resources\AuditLogs\AuditLogResource::class => 'guards',
        Pages\ToolGuardBoard::class => 'guards',
        Pages\RegisterGuardBoard::class => 'guards',
        Resources\Users\UserResource::class => 'guards',
    ];

    /** Cross-listed pages that serve two areas (Costs & launch holds both Cost Board and Launch Scorecard). */
    public const ALSO_IN = [
        Pages\CostsAndLaunch::class => ['overview'],
    ];

    /** @var array<string, list<string>>|null url path → areas */
    protected static ?array $urls = null;

    public static function on(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && auth()->user()?->role === 'super_admin'
            && ! request()->routeIs(
                'filament.admin.auth.login',
                'filament.admin.auth.password-reset.*',
                'filament.admin.auth.multi-factor-authentication.*',
            );
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'overview' => __('Overview'),
            'growth' => __('Growth'),
            'platform' => __('Platform'),
            'guards' => __('Guards'),
            'all' => __('All'),
        ];
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

    /** @return list<string> */
    public static function areasOf(?string $class): array
    {
        if ($class === null) {
            return [];
        }
        if (is_subclass_of($class, ResourcePage::class)) {
            $class = $class::getResource();
        }
        $primary = self::MAP[$class] ?? null;
        if ($primary === null) {
            return [];
        }

        return array_values(array_unique(array_merge([$primary], self::ALSO_IN[$class] ?? [])));
    }

    public static function current(): string
    {
        $requested = request()->query('area');
        if (is_string($requested) && ($requested === 'all' || in_array($requested, self::AREAS, true))) {
            session(['admin_shell.area' => $requested]);

            return $requested;
        }

        $saved = session('admin_shell.area');
        if ($saved === 'all') {
            return 'all';
        }

        $uses = request()->route()?->getAction('uses');
        $class = is_string($uses) ? explode('@', $uses)[0] : null;
        $areas = static::areasOf($class);
        if ($areas !== []) {
            if (is_string($saved) && in_array($saved, $areas, true)) {
                return $saved;
            }
            session(['admin_shell.area' => $areas[0]]);

            return $areas[0];
        }

        return is_string($saved) && in_array($saved, self::AREAS, true) ? $saved : 'overview';
    }

    public static function forget(): void
    {
        static::$urls = null;
        app()->forgetInstance(NavigationManager::class);
    }

    protected static function path(?string $url): string
    {
        $trimmed = rtrim((string) parse_url((string) $url, PHP_URL_PATH), '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /** @return array<string, list<string>> */
    protected static function urls(): array
    {
        if (static::$urls !== null) {
            return static::$urls;
        }
        $out = [];
        foreach (self::MAP as $class => $primary) {
            try {
                $url = is_subclass_of($class, Resource::class) ? $class::getUrl('index') : $class::getUrl();
                $out[static::path($url)] = array_values(array_unique(array_merge([$primary], self::ALSO_IN[$class] ?? [])));
            } catch (Throwable) {
            }
        }

        return static::$urls = $out;
    }

    /** @return list<string>|null null when unmapped (shown in every area) */
    public static function itemAreas(NavigationItem $item): ?array
    {
        return static::urls()[static::path($item->getUrl())] ?? null;
    }

    /**
     * @param  array<NavigationGroup>  $groups
     * @return array<NavigationGroup>
     */
    public static function filter(array $groups, string $area): array
    {
        if ($area === 'all') {
            return $groups;
        }

        $out = [];
        foreach ($groups as $group) {
            $items = collect($group->getItems())->filter(function (NavigationItem $item) use ($area): bool {
                $areas = static::itemAreas($item);

                return $areas === null || in_array($area, $areas, true);
            })->values();

            if ($items->isNotEmpty()) {
                $out[] = (clone $group)->items($items->all());
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{url: string, count: int}>
     */
    public static function areaNavigation(): array
    {
        $links = [];
        $counts = array_fill_keys(self::AREAS, 0);
        $total = 0;

        foreach (ShellNavigationManager::allGroups() as $group) {
            foreach ($group->getItems() as $item) {
                $total++;
                $areas = static::itemAreas($item);
                if ($areas === null) {
                    foreach (self::AREAS as $a) {
                        $counts[$a]++;
                    }

                    continue;
                }
                foreach ($areas as $index => $a) {
                    $counts[$a] = ($counts[$a] ?? 0) + 1;
                    if ($index === 0 && ! isset($links[$a])) {
                        $base = (string) $item->getUrl();
                        $links[$a] = $base.(str_contains($base, '?') ? '&' : '?').'area='.$a;
                    }
                }
            }
        }

        $out = [];
        foreach (self::AREAS as $area) {
            if (isset($links[$area])) {
                $out[$area] = [
                    'url' => $links[$area],
                    'count' => $counts[$area],
                ];
            }
        }
        $out['all'] = [
            'url' => request()->fullUrlWithQuery(['area' => 'all']),
            'count' => $total,
        ];

        return $out;
    }

    /**
     * Ground-truth live telemetry ribbon data for /admin (zero invented metrics).
     *
     * @return array{
     *     workspaces_active: int,
     *     workspaces_total: int,
     *     modules_enabled: list<string>,
     *     modules_total: int,
     *     preview_agencies: list<string>,
     *     heartbeat_ok: bool,
     *     heartbeat_last: ?string,
     *     heartbeat_age_minutes: ?int,
     *     queue_driver: string,
     *     queue_stalled: bool,
     *     failed_jobs_24h: int,
     *     missed_jobs: int,
     *     ops_state: string,
     *     reach_cache_store: string,
     *     app_url: string
     * }
     */
    public static function ribbon(): array
    {
        $workspacesActive = Agency::where('status', 'active')->count();
        $workspacesTotal = Agency::count();

        $moduleKeys = array_keys((array) config('modules.enabled', []));
        $enabledModules = array_values(array_filter($moduleKeys, fn (string $m): bool => Modules::enabled($m)));
        $previewAgencies = Modules::previewAgencies();

        $hb = Heartbeat::status();
        $queueDriver = (string) config('queue.default', 'sync');
        $queueStalled = false;
        if (Schema::hasTable('jobs')) {
            $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
            $queueStalled = $oldest !== null && (int) $oldest < now()->subMinutes(15)->getTimestamp();
        }
        $failed24h = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count()
            : 0;

        $missedJobs = 0;
        try {
            if (Schema::hasTable('job_runs')) {
                $missedJobs = count(JobRuns::problems());
            }
        } catch (Throwable) {
            $missedJobs = 0;
        }

        $opsState = 'ok';
        if (($hb['last'] !== null && ! $hb['ok']) || $queueStalled || $failed24h > 0 || $missedJobs > 0) {
            $opsState = 'warn';
        } elseif ($hb['last'] === null) {
            $opsState = 'pending';
        }

        $reachCacheStore = (string) (config('reach.cache_store') ?: config('cache.default', 'file'));

        return [
            'workspaces_active' => $workspacesActive,
            'workspaces_total' => $workspacesTotal,
            'modules_enabled' => $enabledModules,
            'modules_total' => count($moduleKeys),
            'preview_agencies' => $previewAgencies,
            'heartbeat_ok' => $hb['ok'],
            'heartbeat_last' => $hb['last'],
            'heartbeat_age_minutes' => $hb['age_minutes'],
            'queue_driver' => $queueDriver,
            'queue_stalled' => $queueStalled,
            'failed_jobs_24h' => $failed24h,
            'missed_jobs' => $missedJobs,
            'ops_state' => $opsState,
            'reach_cache_store' => $reachCacheStore,
            'app_url' => url('/app'),
        ];
    }

    public static function density(): string
    {
        return (auth()->user()?->ui_prefs['density'] ?? 'comfortable') === 'compact' ? 'compact' : 'comfortable';
    }

    /**
     * Live A-12 verification of config/feature_register.php for /admin › Guards.
     *
     * @return array{total: int, passing: int, rows: list<array{id: string, title: string, ok: bool, code: list<string>, tests: list<string>, missing: list<string>}>}
     */
    public static function registerAudit(): array
    {
        $configured = (array) config('feature_register', []);
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->all();
        $rows = [];
        $passing = 0;

        foreach ($configured as $id => $row) {
            $missing = [];
            $code = array_values((array) ($row['code'] ?? []));
            $tests = array_values((array) ($row['tests'] ?? []));

            if ($code === [] || $tests === []) {
                $missing[] = 'anchors';
            }

            foreach ($code as $anchor) {
                $ok = match (true) {
                    str_starts_with($anchor, 'view:') => view()->exists(substr($anchor, 5)),
                    str_starts_with($anchor, 'route:') => in_array(substr($anchor, 6), $uris, true),
                    str_starts_with($anchor, 'config:') => is_file(config_path(substr($anchor, 7).'.php')),
                    default => class_exists($anchor),
                };
                if (! $ok) {
                    $missing[] = $anchor;
                }
            }

            foreach ($tests as $test) {
                $file = base_path('tests/'.$test);
                if (! is_file($file) || ! preg_match('/function test_\w+\(/', (string) file_get_contents($file))) {
                    $missing[] = $test;
                }
            }

            $rowOk = $missing === [];
            if ($rowOk) {
                $passing++;
            }

            $rows[] = [
                'id' => (string) $id,
                'title' => (string) ($row['title'] ?? $id),
                'ok' => $rowOk,
                'code' => array_map(fn (string $c): string => str_contains($c, '\\') ? class_basename($c) : $c, $code),
                'tests' => $tests,
                'missing' => $missing,
            ];
        }

        return [
            'total' => count($rows),
            'passing' => $passing,
            'rows' => $rows,
        ];
    }
}
