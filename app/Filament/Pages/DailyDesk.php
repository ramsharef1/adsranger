<?php

namespace App\Filament\Pages;

use App\Connectors\ConnectorRegistry;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Jobs\SyncAdAccount;
use App\Models\AdAccount;
use App\Models\AiBrainItem;
use App\Models\Alert;
use App\Models\Client;
use App\Support\CurrentClient;
use App\Support\MetricsQuery;
use App\Support\Modules;
use App\Support\Periods;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** Daily desk: every client for one day in its own currency, every goal type. */
class DailyDesk extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Results';

    protected static ?string $navigationLabel = 'Daily desk';

    protected static ?string $title = 'Daily desk';

    protected static ?string $slug = 'desk';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.daily-desk';

    public string $day = 'yesterday';

    public ?string $date = null;

    /** D-188: the owner hides the "Finish setup" checklist. */
    public function hideOnboardingChecklist(): void
    {
        $a = Filament::getTenant();
        if ($a->members()->whereKey(auth()->id())->wherePivotIn('role', ['owner', 'admin'])->exists()) {
            $a->forceFill(['onboarding_checklist_hidden' => true])->save();
        }
    }

    public function setDay(string $d): void
    {
        $this->day = in_array($d, ['yesterday', 'today', 'custom'], true) ? $d : 'yesterday';
    }

    /** Today is live: re-pull today's numbers at most every 10 minutes per workspace. */
    public function refreshToday(): void
    {
        $a = Filament::getTenant();
        $key = 'desk-today:'.$a->id;
        if (Cache::has($key)) {
            Notification::make()->title(__('Today was refreshed less than 10 minutes ago'))->body(__('Numbers update every 10 minutes at most.'))->info()->send();

            return;
        }
        Cache::put($key, now()->timestamp, 600);
        $n = 0;
        $today = now($a->timezone ?: 'UTC')->toDateString();
        AdAccount::with('network')->where('agency_id', $a->id)->where('is_active', true)->whereNotNull('client_id')->get()
            ->filter(fn ($acct) => $acct->network && ConnectorRegistry::for($acct->network)->supportsSync())
            ->each(function ($acct) use ($today, &$n) {
                SyncAdAccount::dispatch($acct->id, $today, $today, 'today');
                $n++;
            });
        $this->day = 'today';
        Notification::make()->title($n ? trans_choice('{1} Refreshing :count account|[2,*] Refreshing :count accounts', $n) : __('No connected accounts to refresh'))->body($n ? __('Reload in a minute or two.') : null)->success()->send();
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $tz = $a->timezone ?: 'UTC';
        [$from, $to] = Periods::range($this->day, $tz, $this->date);
        $clients = CurrentClient::apply(Client::visibleTo(auth()->user(), $a)->where('status', 'active'), 'clients.id', $a)->orderBy('name')->get();
        $alerts = Alert::where('agency_id', $a->id)->where('status', 'open')->whereIn('client_id', $clients->pluck('id'))
            ->selectRaw('client_id, severity, count(*) as n')->groupBy('client_id', 'severity')->get()->groupBy('client_id');
        $rows = $clients->map(fn (Client $c) => ['client' => $c, 't' => MetricsQuery::totals($c, $from, $to), 'alerts' => $alerts[$c->id] ?? collect()]);
        $synced = AdAccount::where('agency_id', $a->id)->where('is_active', true)->max('last_synced_at');

        // EG-26 (R7): the night shift's findings from the last 24 h, one card per item, with useful / not useful (FS-08).
        $night = (Modules::on('ai_workspace', null, $a) || Modules::isPreviewAgency($a))
            ? AiBrainItem::where('agency_id', $a->id)->where('playbook', 'night_shift')->whereIn('client_id', $clients->pluck('id'))
                ->where('created_at', '>=', now()->subDay())->latest()->limit(12)->get()
                ->reject(fn (AiBrainItem $i) => isset($i->meta['feedback']))->values()
            : collect();

        // WS-34 (D-301): client brains nobody has checked in 30 days (only clients with something saved), for the people who see them.
        $toCheck = (Modules::on('ai_workspace', null, $a) || Modules::isPreviewAgency($a))
            ? $clients->filter(fn (Client $c) => ! $c->brain_checked_at || $c->brain_checked_at->lt(now()->subDays(30)))
                ->whereIn('id', AiBrainItem::where('agency_id', $a->id)->whereIn('client_id', $clients->pluck('id'))->distinct()->pluck('client_id'))
                ->take(5)->values()
            : collect();

        return [
            'from' => $from, 'rows' => $rows, 'synced' => $synced ? Carbon::parse($synced) : null,
            'tz' => $tz, 'night' => $night, 'clientNames' => $clients->pluck('name', 'id'), 'brainsToCheck' => $toCheck,
        ];
    }

    /** FS-08: the member says whether a night-shift item helped; kept in the item, counted per level on the night shift page. */
    public function nightFeedback(int $id, bool $useful): void
    {
        $a = Filament::getTenant();
        $item = AiBrainItem::where('agency_id', $a->id)->where('playbook', 'night_shift')->findOrFail($id);
        abort_unless(Client::visibleTo(auth()->user(), $a)->whereKey($item->client_id)->exists(), 403);
        $item->forceFill(['meta' => array_merge($item->meta ?? [], ['feedback' => $useful ? 'useful' : 'not_useful', 'feedback_by' => auth()->id()])])->save();
    }
}
