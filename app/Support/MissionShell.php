<?php

namespace App\Support;

use App\Filament\Pages;
use App\Filament\Resources\Alerts\AlertResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use Filament\Facades\Filament;

/**
 * Mission control shell (D-312, design canvas "AdsRanger Admin Revamp"): a dark look on top of the panel shell
 * (PX-01 / PX-02), with an icon rail for the areas and the add-ons, a "next step" side panel, a desktop dock with the
 * clients that need attention, and a simple / expert mode. Behind the `mission_shell` module switch; each person can
 * go back to the classic look from the account menu. Every number shown comes from the workspace's own alerts and
 * clients; nothing is estimated here.
 */
class MissionShell
{
    /** area or add-on → SVG path (24 px, stroke) */
    public const ICONS = [
        'today' => 'M3 12l9-8 9 8M5 10v10h14V10',
        'clients' => 'M16 19v-1a4 4 0 00-4-4H6a4 4 0 00-4 4v1M9 10a3 3 0 100-6 3 3 0 000 6M22 19v-1a4 4 0 00-3-3.9M16 4.1a3 3 0 010 5.8',
        'ads' => 'M3 11l15-6v14L3 13zM7 12v6a2 2 0 004 0v-5',
        'seo' => 'M11 18a7 7 0 100-14 7 7 0 000 14zM21 21l-5-5',
        'ai' => 'M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2z',
        'reports' => 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6',
        'leads' => 'M4 5h16v11H9l-5 4z',
        'store' => 'M3 5h2l2 11h11l2-8H7M9 20a1 1 0 100-2 1 1 0 000 2M17 20a1 1 0 100-2 1 1 0 000 2',
        'social' => 'M4 5h16v12H4zM8 21h8M10 8l5 3-5 3z',
        'office' => 'M4 21V5h10v16M14 9h6v12M8 9h2M8 13h2M8 17h2',
    ];

    /** Areas a beginner does not need on day one; simple mode leaves them out of the rail. */
    public const EXPERT_ONLY = ['seo', 'ai'];

    public static function available(?Agency $agency = null): bool
    {
        $agency ??= Filament::getTenant();

        return $agency instanceof Agency && Shell::on($agency) && Modules::on('mission_shell', null, $agency);
    }

    /** On when the switch allows it and this person has not chosen the classic look. */
    public static function on(?Agency $agency = null): bool
    {
        return static::available($agency) && static::look() === 'mission';
    }

    public static function look(): string
    {
        return (auth()->user()?->ui_prefs['look'] ?? 'mission') === 'classic' ? 'classic' : 'mission';
    }

    public static function mode(): string
    {
        return (auth()->user()?->ui_prefs['mode'] ?? 'expert') === 'simple' ? 'simple' : 'expert';
    }

    /** @return array<string, array{label: string, module: string, page: ?class-string}> add-on → its switch and first page */
    public static function addOns(): array
    {
        return [
            'leads' => ['label' => __('Leads'), 'module' => 'lead_desk', 'page' => null],
            'store' => ['label' => __('Store'), 'module' => 'intelligence_hub', 'page' => Pages\ProfitAttributionHubPage::class],
            'social' => ['label' => __('Social'), 'module' => 'organic', 'page' => Pages\OrganicSocialPage::class],
            'office' => ['label' => __('Agency office'), 'module' => 'agency_ops_hub', 'page' => Pages\AgencyOpsHubPage::class],
        ];
    }

    /**
     * The icon rail: the shell areas this person can open, then the add-ons. An add-on whose switch is off, or that
     * has no page yet, shows as locked and leads to Plan & usage.
     *
     * @return list<array{key: string, label: string, url: string, icon: string, current: bool, locked: bool}>
     */
    public static function rail(Agency $agency): array
    {
        $simple = static::mode() === 'simple';
        $current = Shell::current();
        $labels = Shell::labels();
        $out = [];
        foreach (Shell::areaLinks() as $area => $url) {
            if ($simple && in_array($area, self::EXPERT_ONLY, true)) {
                continue;
            }
            $out[] = ['key' => $area, 'label' => $labels[$area], 'url' => $url, 'icon' => self::ICONS[$area], 'current' => $area === $current, 'locked' => false];
        }
        foreach (static::addOns() as $key => $a) {
            $page = $a['page'];
            $open = $page !== null && Modules::on($a['module'], null, $agency) && $page::canAccess();
            if ($simple && ! $open) {
                continue;
            }
            $out[] = [
                'key' => $key, 'label' => $a['label'], 'icon' => self::ICONS[$key], 'locked' => ! $open,
                'url' => $open ? $page::getUrl() : Pages\PlanUsage::getUrl(),
                'current' => $open && request()->routeIs($page::getRouteName()),
            ];
        }

        return $out;
    }

    /**
     * The one thing to do next: the most severe open alert on a client this person sees, oldest first.
     *
     * @return ?array{title: string, client: string, level: string, url: string}
     */
    public static function nextStep(Agency $agency): ?array
    {
        $ids = Client::visibleTo(auth()->user(), $agency)->where('status', 'active')->pluck('name', 'id');
        if ($ids->isEmpty()) {
            return null;
        }
        $a = Alert::where('agency_id', $agency->id)->where('status', 'open')->whereIn('client_id', $ids->keys())
            ->orderByRaw("case when severity in ('critical', 'high') then 0 when severity in ('warning', 'medium') then 1 else 2 end")
            ->orderBy('created_at')->first();
        if (! $a) {
            return null;
        }

        return [
            'title' => (string) $a->title,
            'client' => (string) ($ids[$a->client_id] ?? ''),
            'level' => in_array($a->severity, ['critical', 'high'], true) ? 'critical' : (in_array($a->severity, ['warning', 'medium'], true) ? 'warning' : 'info'),
            'url' => AlertResource::getUrl('view', ['record' => $a]),
        ];
    }

    /**
     * The desktop dock: up to 6 clients, the ones with open alerts first.
     *
     * @return list<array{name: string, level: string, url: string}>
     */
    public static function dock(Agency $agency): array
    {
        $rank = ['critical' => 0, 'warning' => 1, 'ok' => 2];

        return collect(Shell::clientsWithStatus($agency))
            ->sortBy(fn (array $c) => $rank[$c['level']])->take(6)
            ->map(fn (array $c) => ['name' => $c['name'], 'level' => $c['level'], 'url' => ClientResource::getUrl('view', ['record' => $c['id']])])
            ->values()->all();
    }

    /**
     * Simple mode: the first steps of a new workspace, each checked against real records.
     *
     * @return list<array{label: string, done: bool, url: string}>
     */
    public static function coach(Agency $agency): array
    {
        $clients = Client::where('agency_id', $agency->id);
        $hasClient = (clone $clients)->exists();
        $hasTarget = (clone $clients)->where(fn ($q) => $q->where('target_cpr', '>', 0)->orWhere('target_roas', '>', 0))->exists();
        $hasAccount = AdAccount::where('agency_id', $agency->id)->exists();

        return [
            ['label' => __('Add your first client'), 'done' => $hasClient, 'url' => ClientResource::getUrl('index')],
            ['label' => __('Connect an ad account or upload a file'), 'done' => $hasAccount, 'url' => Pages\Networks::getUrl()],
            ['label' => __('Set a target for one client'), 'done' => $hasTarget, 'url' => ClientResource::getUrl('index')],
            ['label' => __('Open today\'s list'), 'done' => $hasClient && $hasAccount, 'url' => Pages\DailyDesk::getUrl()],
        ];
    }
}
