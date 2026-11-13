<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Alert;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLicence;
use App\Support\MenaCalendar;
use App\Support\MetricsQuery;
use App\Support\Periods;
use App\Support\ResultResolver;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * XS-07 / PX-39 (R4): one page per client meeting: last 30 days vs the 30 before, what worked, open alerts, the next
 * season, and what needs a decision. Presentation mode hides internal notes for screen sharing and says so on screen.
 */
class MeetingPrepPage extends Page
{
    use TranslatesPageLabels;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Meeting prep';

    protected static ?string $slug = 'meeting-prep';

    protected string $view = 'filament.pages.meeting-prep';

    #[Url]
    public ?int $client = null;

    #[Url]
    public bool $present = false;

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $c = Client::visibleTo(auth()->user(), $a)->findOrFail($this->client);
        $tz = $a->timezone ?: 'UTC';
        [$from, $to] = Periods::range('30d', $tz);
        [$pf, $pt] = Periods::previous($from, $to);
        $today = now($tz)->toDateString();

        return [
            'c' => $c, 'from' => $from, 'to' => $to,
            't' => MetricsQuery::totals($c, $from, $to), 'prev' => MetricsQuery::totals($c, $pf, $pt),
            'top' => array_slice(array_values(array_filter(MetricsQuery::breakdown($c, $from, $to, 'campaign'), fn ($r) => ($r['main_results'] ?? 0) > 0)), 0, 3),
            'alerts' => Alert::where('client_id', $c->id)->where('status', 'open')->whereIn('severity', ['critical', 'warning', 'high', 'medium'])->latest()->limit(5)->get(),
            'season' => collect(MenaCalendar::events($today, now($tz)->addDays(60)->toDateString(), $a->country ?: null))->first(fn ($e) => $e['start'] >= $today),
            'decisions' => array_values(array_filter([
                $c->monthly_budget ? null : __('Agree a monthly budget'),
                ($c->target_cpr || $c->target_results || $c->target_roas) ? null : __('Agree targets (cost per result or results)'),
                ClientLicence::where('client_id', $c->id)->whereNotNull('expires_on')->where('expires_on', '<=', now($tz)->addDays(30)->toDateString())->exists() ? __('Licence renewal') : null,
            ])),
            'notes' => $this->present ? collect() : ClientContact::where('client_id', $c->id)->whereNotNull('note')->orderByDesc('happened_on')->limit(3)->get(),
            'result' => fn (?string $type) => ResultResolver::label($type, app()->getLocale()),
        ];
    }

    public function getTitle(): string
    {
        return __('Meeting prep');
    }
}
