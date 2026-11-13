<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AdAccount;
use App\Models\AiConnection;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Support\AiWorkspace\FreePlan;
use App\Support\MenaCalendar;
use App\Support\MetricsQuery;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Free plan lite view (Product M1 screens §6, spec v2 §3; D-286): campaigns for the last 30 days with ad sets and ads one
 * level down, a weekly summary built without AI, the controls shown as «قريبًا», and one banner at a time in the §6
 * order. Never hides or deletes saved data.
 */
class FreeLiteView extends Page
{
    use HeldBackModule;
    use TranslatesPageLabels;

    protected static string $module = 'ai_workspace';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'AI Workspace';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?string $title = 'Campaigns';

    protected static ?string $slug = 'lite';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.free-lite-view';

    public static function shouldRegisterNavigation(): bool
    {
        return FreePlan::isFree(Filament::getTenant()) && parent::shouldRegisterNavigation();
    }

    /** The one banner to show, in the §6 priority order. @return array{0: string, 1: string}|null [kind, text] */
    protected function banner($agency, $accounts): ?array
    {
        if (! FreePlan::aiConnected($agency)) {
            return ['connect', __('Connect your AI to turn on the tools. Your data is kept.')];
        }
        $cap = FreePlan::dailyCap($agency);
        $today = now($agency->timezone ?: 'UTC')->toDateString();
        if (AiConnection::where('agency_id', $agency->id)->whereDate('calls_day', $today)->where('calls_today', '>=', $cap)->exists()) {
            return ['cap', __('You reached today\'s limit of :n requests. It resets at midnight, workspace time.', ['n' => number_format($cap)])];
        }
        if ($accounts->contains(fn ($a) => $a->is_active && $a->last_synced_at && Carbon::parse($a->last_synced_at)->lt(now()->subDays(25)))) {
            return ['paused', __('We paused updates after 30 days without activity. We are updating your data now.')];
        }
        $fence = FreePlan::spendFence($agency);
        if ($fence['over']) {
            return ['fence', __('Your ad spend passed :cap USD a month for two months. AI tools become view-only on :date unless you move to a paid plan. Nothing is deleted.',
                ['cap' => number_format($fence['cap']), 'date' => $fence['view_only_from']])];
        }

        return null;
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $client = Client::visibleTo(auth()->user(), $agency)->orderBy('id')->first();
        $accounts = AdAccount::where('agency_id', $agency->id)->get();
        $refused = $accounts->filter(fn ($a) => ! $a->is_active && (FreePlan::adAccountTaken($a) || FreePlan::platformDisabled($a)));

        $campaigns = [];
        $summary = null;
        $until = null;
        if ($client) {
            $until = DailyMetric::where('agency_id', $agency->id)->where('client_id', $client->id)->max('date');
            $to = now($agency->timezone ?: 'UTC')->toDateString();
            $from = now($agency->timezone ?: 'UTC')->subDays(29)->toDateString();
            $last7 = collect(MetricsQuery::breakdown($client, now()->subDays(6)->toDateString(), $to))->keyBy('ref');
            $prev7 = collect(MetricsQuery::breakdown($client, now()->subDays(13)->toDateString(), now()->subDays(7)->toDateString()))->keyBy('ref');
            $sets = collect(MetricsQuery::breakdown($client, $from, $to, 'ad_set'));
            $ads = collect(MetricsQuery::breakdown($client, $from, $to, 'ad'));
            $setParent = DB::table('ad_sets')->whereIn('id', $sets->pluck('ref'))->pluck('campaign_id', 'id');
            $adParent = DB::table('ads')->whereIn('id', $ads->pluck('ref'))->pluck('ad_set_id', 'id');
            foreach (MetricsQuery::breakdown($client, $from, $to) as $c) {
                $now = (float) ($last7[$c['ref']]['main_results'] ?? 0);
                $before = (float) ($prev7[$c['ref']]['main_results'] ?? 0);
                $c['trend'] = $now > $before ? 'up' : ($now < $before ? 'down' : 'flat');
                $c['sets'] = $sets->filter(fn ($s) => (int) ($setParent[$s['ref']] ?? 0) === (int) $c['ref'])
                    ->map(fn ($s) => $s + ['ads' => $ads->filter(fn ($a) => (int) ($adParent[$a['ref']] ?? 0) === (int) $s['ref'])->values()->all()])->values()->all();
                $campaigns[] = $c;
            }
            $week = MetricsQuery::totals($client, now()->subDays(6)->toDateString(), $to);
            $ranked = $last7->filter(fn ($c) => $c['spend'] > 0)->sortBy(fn ($c) => $c['cpr'] ?? PHP_INT_MAX)->values();
            $next = collect(MenaCalendar::events($to, now()->addDays(120)->toDateString()))->filter(fn ($e) => $e['start'] >= $to)->sortBy('start')->first();
            $summary = ['spend' => $week['spend'], 'results' => $week['main_results'], 'best' => $ranked->first(), 'worst' => $ranked->count() > 1 ? $ranked->last() : null,
                'season' => $next, 'currency' => $client->currency ?: 'USD'];
        }

        return ['client' => $client, 'campaigns' => $campaigns, 'summary' => $summary, 'until' => $until, 'banner' => $this->banner($agency, $accounts),
            'refused' => $refused, 'tz' => $agency->timezone ?: 'UTC'];
    }
}
