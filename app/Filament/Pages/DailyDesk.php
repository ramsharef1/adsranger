<?php

namespace App\Filament\Pages;

use App\Connectors\ConnectorRegistry;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Jobs\SyncAdAccount;
use App\Models\AdAccount;
use App\Models\Alert;
use App\Models\Client;
use App\Support\MetricsQuery;
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
            Notification::make()->title('Today was refreshed less than 10 minutes ago')->body('Numbers update every 10 minutes at most.')->info()->send();

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
        Notification::make()->title($n ? "Refreshing $n account(s)" : 'No connected accounts to refresh')->body($n ? 'Reload in a minute or two.' : null)->success()->send();
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $tz = $a->timezone ?: 'UTC';
        [$from, $to] = Periods::range($this->day, $tz, $this->date);
        $clients = Client::visibleTo(auth()->user(), $a)->where('status', 'active')->orderBy('name')->get();
        $alerts = Alert::where('agency_id', $a->id)->where('status', 'open')->whereIn('client_id', $clients->pluck('id'))
            ->selectRaw('client_id, severity, count(*) as n')->groupBy('client_id', 'severity')->get()->groupBy('client_id');
        $rows = $clients->map(fn (Client $c) => ['client' => $c, 't' => MetricsQuery::totals($c, $from, $to), 'alerts' => $alerts[$c->id] ?? collect()]);
        $synced = AdAccount::where('agency_id', $a->id)->where('is_active', true)->max('last_synced_at');

        return [
            'from' => $from, 'rows' => $rows, 'synced' => $synced ? Carbon::parse($synced) : null,
            'tz' => $tz,
        ];
    }
}
