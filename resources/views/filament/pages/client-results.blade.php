@php
    use App\Support\ResultResolver as R;
    $cur = $client->currency;
    $delta = function ($now, $prev, $lowerIsBetter = false) {
        if (! $prev || $now === null) return null;
        $p = ($now / $prev - 1) * 100;
        $good = $lowerIsBetter ? $p < 0 : $p > 0;
        return ['pct' => $p, 'good' => abs($p) < 0.5 ? null : $good];
    };
    $kpis = [
        [__('Spend'), R::money($t['spend'], $cur), $delta($t['spend'], $prev['spend']), null],
        [R::label($t['main_type'], app()->getLocale()) ?: __('Results'), number_format($t['main_results']), $delta($t['main_results'], $prev['main_results']), __('Main goal by spend')],
        [__('Cost per result'), R::money($t['cpr'], $cur), $delta($t['cpr'], $prev['cpr'], true), null],
        [__('ROAS'), $t['roas'] ? number_format($t['roas'], 2).'×' : '–', $delta($t['roas'], $prev['roas']), __('Purchase / conversion goals only')],
        [__('Link CTR'), $t['ctr'] ? number_format($t['ctr'], 2).'%' : '–', $delta($t['ctr'], $prev['ctr']), null],
    ];
    $goals = array_filter([
        'Leads' => $t['leads'], 'Conversations' => $t['messages'],
        'Purchases' => $t['purchases'], 'Purchase value' => $t['revenue'] > 0 ? R::money($t['revenue'], $cur) : 0,
        'ThruPlays' => $t['thruplays'], 'Landing page views' => $t['landing_views'], 'Link clicks' => $t['link_clicks'],
    ]);
    $maxSpend = max(1, collect($months)->max('spend'));
    $maxRes = max(1, collect($months)->max('main_results'));
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px';
    $muted = 'opacity:.65;font-size:12px';
    $pill = fn ($on) => 'padding:5px 12px;border-radius:99px;font-size:13px;cursor:pointer;border:1px solid '.($on ? '#14B8A6' : 'rgba(148,163,184,.35)').';background:'.($on ? 'rgba(20,184,166,.12)' : 'transparent');
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:6px">
            @foreach ($periods as $k => $label)
                <button type="button" wire:click="setPeriod('{{ $k }}')" style="{{ $pill($period === $k) }}">{{ __($label) }}</button>
            @endforeach
        </div>
        <div style="{{ $muted }}">
            {{ $from }} → {{ $to }} · {{ $cur }} ·
            @if ($synced) {{ __('Data updated') }} {{ $synced->diffForHumans() }} @else {{ __('No sync yet') }} @endif
            @if ($accounts->isEmpty()) · <a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('edit', ['record' => $client]) }}" style="color:#14B8A6">{{ __('link an ad account') }}</a> @endif
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
        @foreach ($kpis as [$label, $value, $d, $hint])
            <div style="{{ $card }}">
                <div style="{{ $muted }}">{{ $label }}</div>
                <div style="font-size:22px;font-weight:700;margin-top:4px">{{ $value }}</div>
                <div style="font-size:12px;margin-top:2px">
                    @if ($d)
                        <span style="color:{{ $d['good'] === null ? 'inherit' : ($d['good'] ? '#16a34a' : '#dc2626') }}">{{ $d['pct'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($d['pct']), 1) }}%</span>
                        <span style="opacity:.6">{{ __('vs previous') }}</span>
                    @elseif ($hint)
                        <span style="opacity:.6">{{ $hint }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @php [$pl, $pc] = \App\Support\Pacing::label($pace['status']); @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px">
        <div style="{{ $card }}">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div style="{{ $muted }}">{{ __('Health score (last 14 days)') }}</div>
                <div style="display:flex;gap:6px;align-items:center">@if (! empty($health['no_target_set']))<x-filament::badge color="gray">{{ __('No target set') }}</x-filament::badge>@endif<x-filament::badge :color="$health['color']">{{ __($health['band']) }}</x-filament::badge></div>
            </div>
            <div style="font-size:28px;font-weight:700;margin:4px 0 8px">{{ $health['score'] ?? '–' }}<span style="{{ $muted }}"> / 100</span></div>
            @forelse ($health['parts'] as $k => $part)
                <div style="display:grid;grid-template-columns:90px 1fr 54px;gap:8px;align-items:center;font-size:12px;margin-top:4px">
                    <div>{{ __(['pacing' => 'Pacing', 'alerts' => 'Alerts & ads', 'targets' => 'Targets', 'results' => 'Results', 'trend' => 'Trend', 'tracking' => 'Tracking'][$k] ?? $k) }}</div>
                    <div style="height:8px;border-radius:6px;background:rgba(148,163,184,.18);overflow:hidden" role="img" aria-label="{{ __(':points of :max', ['points' => $part['points'], 'max' => $part['max']]) }}">
                        <div style="height:8px;width:{{ $part['max'] ? round($part['points'] / $part['max'] * 100) : 0 }}%;background:#14B8A6"></div>
                    </div>
                    <div style="text-align:end">{{ $part['points'] }}/{{ $part['max'] }}</div>
                </div>
                <div style="{{ $muted }};margin:1px 0 0 98px">{{ $part['note'] }}</div>
            @empty
                <div style="{{ $muted }}">{{ __('No spend or results in the last 14 days.') }}</div>
            @endforelse
        </div>
        <div style="{{ $card }}">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div style="{{ $muted }}">{{ __(':month · day :day of :days', ['month' => $pace['month_label'], 'day' => $pace['full_days'], 'days' => $pace['days_in']]) }}</div>
                <x-filament::badge :color="$pc">{{ __($pl) }}</x-filament::badge>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px;font-size:13px">
                <div><div style="{{ $muted }}">{{ $pace['from_plan'] ? __('Budget (plan)') : __('Budget') }}</div><strong>{{ $pace['budget'] ? R::money($pace['budget'], $cur) : '–' }}</strong></div>
                <div><div style="{{ $muted }}">{{ __('Spent so far') }}</div><strong>{{ R::money($pace['spent'], $cur) }}</strong>@if ($pace['pace'] !== null)<span style="{{ $muted }}"> · {{ __(':pct% of expected', ['pct' => round($pace['pace'])]) }}</span>@endif</div>
                @if ($forecastLocked)
                    <div><div style="{{ $muted }}">{{ __('Month-end forecast') }}</div><x-locked-button feature="forecast" /></div>
                @else
                    <div><div style="{{ $muted }}">{{ __('Month-end forecast') }}</div><strong>{{ R::money($pace['forecast_spend'], $cur) }}</strong>@if ($pace['forecast_vs_budget'])<span style="{{ $muted }}"> · {{ __(':pct% of budget', ['pct' => round($pace['forecast_vs_budget'])]) }}</span>@endif</div>
                @endif
                <div><div style="{{ $muted }}">{{ __('Needed per day') }}</div><strong>{{ $pace['daily_needed'] !== null ? R::money($pace['daily_needed'], $cur) : '–' }}</strong></div>
                @if ($forecastLocked)
                    <div><div style="{{ $muted }}">{{ __(':result so far', ['result' => R::label($pace['main_type'], app()->getLocale()) ?: __('Results')]) }}</div><strong>{{ number_format($pace['results']) }}</strong>@if ($pace['target_results'])<span style="{{ $muted }}"> · {{ __('target :n', ['n' => number_format($pace['target_results'])]) }}</span>@endif</div>
                    <div><div style="{{ $muted }}">{{ __('Forecast cost / result') }}</div><x-locked-button feature="forecast" /></div>
                @else
                    <div><div style="{{ $muted }}">{{ __(':result so far → forecast', ['result' => R::label($pace['main_type'], app()->getLocale()) ?: __('Results')]) }}</div><strong>{{ number_format($pace['results']) }} → {{ number_format($pace['forecast_results']) }}</strong>@if ($pace['target_results'])<span style="{{ $muted }}"> · {{ __('target :n', ['n' => number_format($pace['target_results'])]) }}</span>@endif</div>
                    <div><div style="{{ $muted }}">{{ __('Forecast cost / result') }}</div><strong>{{ R::money($pace['forecast_cpr'], $cur) }}</strong></div>
                @endif
            </div>
            <div style="{{ $muted }};margin-top:8px">{{ __('Forecast = spent so far + last :days days\' daily average × :left days left.', ['days' => config('planning.pacing.run_rate_days'), 'left' => $pace['days_left']]) }}
                <a href="{{ \App\Filament\Pages\BudgetPlanner::getUrl(['client' => $client->id]) }}" style="color:#14B8A6">{{ __('Budget plan') }}</a> ·
                <a href="{{ \App\Filament\Pages\InsightsPage::getUrl(['client' => $client->id]) }}" style="color:#14B8A6">{{ __('Insights') }}</a></div>
        </div>
    </div>

    @if ($goals || count($t['by_type']) > 1)
        <x-filament::section>
            <x-slot name="heading">{{ __('Results by goal') }}</x-slot>
            <div style="display:flex;flex-wrap:wrap;gap:18px">
                @foreach ($t['by_type'] as $type => $n)
                    <div><div style="{{ $muted }}">{{ __(':result (goal)', ['result' => R::label($type, app()->getLocale())]) }}</div><div style="font-weight:600">{{ number_format($n) }}
                        @if (($t['type_spend'][$type] ?? 0) > 0 && $n > 0)<span style="{{ $muted }}"> · {{ __(':amount each', ['amount' => R::money($t['type_spend'][$type] / $n, $cur)]) }}</span>@endif</div></div>
                @endforeach
                @foreach ($goals as $label => $n)
                    <div><div style="{{ $muted }}">{{ __($label) }}</div><div style="font-weight:600">{{ is_numeric($n) ? number_format($n) : $n }}</div></div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @php $calls = \App\Support\CallResults::summary($t); $callBasis = \App\Support\CallResults::basis($client->call_result); @endphp
    @if ($calls)
        <x-filament::section>
            <x-slot name="heading">{{ __('Calls') }}</x-slot>
            <x-slot name="description">{{ __('Counted as the result: :basis', ['basis' => \App\Support\CallResults::options()[$callBasis]]) }}</x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
                <div><div style="{{ $muted }}">{{ __('Calls placed') }}</div><div style="font-size:20px;font-weight:700">{{ number_format($calls['placed']) }}</div>
                    @if ($calls['cost_placed'])
                        <div style="{{ $muted }}">{{ __(':amount each', ['amount' => R::money($calls['cost_placed'], $cur)]) }}</div>
                    @endif
                </div>
                @if ($calls['split'])
                    <div><div style="{{ $muted }}">{{ __('Calls confirmed') }}</div><div style="font-size:20px;font-weight:700">{{ number_format($calls['confirmed']) }}</div>
                        @if ($calls['confirm_rate'] !== null)
                            <div style="{{ $muted }}">{{ __(':pct% of calls placed', ['pct' => number_format($calls['confirm_rate'], 0)]) }}</div>
                        @endif
                    </div>
                    <div><div style="{{ $muted }}">{{ __('Calls 20s+') }}</div><div style="font-size:20px;font-weight:700">{{ number_format($calls['connected']) }}</div>
                        @php
                            $connLine = $calls['connect_rate'] !== null ? __(':pct% of calls placed', ['pct' => number_format($calls['connect_rate'], 0)]) : null;
                            if ($calls['cost_connected']) $connLine = trim(($connLine ? $connLine.' · ' : '').__(':amount each', ['amount' => R::money($calls['cost_connected'], $cur)]));
                        @endphp
                        @if ($connLine)
                            <div style="{{ $muted }}">{{ $connLine }}</div>
                        @endif
                    </div>
                @endif
            </div>
            <div style="{{ $muted }};margin-top:8px">{{ $calls['split'] ? __('A call of 20 seconds or more is the closest sign of a real conversation. Change what counts in the client settings.') : __('This network reports one call count.') }}</div>
        </x-filament::section>
    @endif

    {{-- XS-10 (R4): paused client --}}
    @if ($client->status === 'paused')
        <div role="status" data-client-paused style="border:1px solid rgba(180,83,9,.4);background:rgba(245,158,11,.10);border-radius:12px;padding:10px 14px;font-size:14px">
            <strong>{{ __('Paused') }}</strong> · {{ \App\Support\ClientFreeze::reasonLabels()[$client->paused_reason] ?? __('Other') }}
            @if ($client->paused_until) · {{ __('Resumes on :date', ['date' => $client->paused_until->locale(app()->getLocale())->translatedFormat('j M Y')]) }}@endif
            <div style="{{ $muted }}">{{ __('Alerts, scheduled reports and the night shift are stopped. Data keeps syncing.') }}</div>
        </div>
    @endif

    {{-- FS-16 / XS-14 (R4, D-304): COD delivered orders, last 8 weeks --}}
    @if ($cod)
        <x-filament::section data-cod>
            <x-slot name="heading">{{ __('Cash on delivery: delivered orders') }}</x-slot>
            <x-slot name="description">{{ __('Last 8 weeks, from the counts your team entered. Ad spend is for the same weeks.') }}</x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
                <div><div style="{{ $muted }}">{{ __('Delivered') }}</div><strong>{{ number_format($cod['delivered']) }}</strong> <span style="{{ $muted }}">{{ __('of :n orders', ['n' => number_format($cod['placed'])]) }}</span></div>
                <div><div style="{{ $muted }}">{{ __('Delivery rate') }}</div><strong>{{ $cod['delivery_rate'] !== null ? $cod['delivery_rate'].'%' : '–' }}</strong></div>
                <div><div style="{{ $muted }}">{{ __('Cost per delivered order') }}</div><strong>{{ $cod['cost_per_delivered'] !== null ? R::money($cod['cost_per_delivered'], $cur) : '–' }}</strong></div>
                <div><div style="{{ $muted }}">{{ __('Cost per order placed') }}</div><strong>{{ $cod['cost_per_placed'] !== null ? R::money($cod['cost_per_placed'], $cur) : '–' }}</strong></div>
            </div>
            <div style="overflow-x:auto;margin-top:10px">
                <table style="width:100%;font-size:13px;border-collapse:collapse">
                    <thead><tr><th style="text-align:start;padding:6px">{{ __('Week starting') }}</th><th style="text-align:start;padding:6px">{{ __('Campaign') }}</th><th style="text-align:end;padding:6px">{{ __('Delivered') }}</th><th style="text-align:end;padding:6px">{{ __('Returned') }}</th><th style="text-align:end;padding:6px">{{ __('Cancelled') }}</th></tr></thead>
                    <tbody>@foreach ($codWeeks as $w)<tr style="border-top:1px solid rgba(148,163,184,.2)"><td style="padding:6px"><bdi>{{ $w->week_start->toDateString() }}</bdi></td><td style="padding:6px"><bdi>{{ $w->campaign?->name ?? __('All campaigns') }}</bdi></td><td style="padding:6px;text-align:end">{{ number_format($w->delivered) }}</td><td style="padding:6px;text-align:end">{{ number_format($w->returned) }}</td><td style="padding:6px;text-align:end">{{ number_format($w->cancelled) }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- XS-02 (R4): contact log --}}
    <x-filament::section data-contacts>
        <x-slot name="heading">{{ __('Contact log') }}</x-slot>
        @if ($contacts->isEmpty())
            <p style="{{ $muted }};margin:0">{{ __('No calls, chats or meetings logged yet. Use "Log contact" above.') }}</p>
        @else
            @foreach ($contacts as $ct)
                <div style="padding:6px 0;border-top:1px solid rgba(148,163,184,.2);font-size:14px">
                    <strong>{{ \App\Models\ClientContact::kindLabels()[$ct->kind] ?? $ct->kind }}</strong>
                    · <bdi>{{ $ct->happened_on->locale(app()->getLocale())->translatedFormat('j M Y') }}</bdi>@if ($ct->user) · <bdi>{{ $ct->user->name }}</bdi>@endif
                    @if ($ct->note)<div dir="auto" style="white-space:pre-line">{{ $ct->note }}</div>@endif
                    @if ($ct->next_on)<div style="{{ $muted }}">{{ __('Next contact: :date', ['date' => $ct->next_on->locale(app()->getLocale())->translatedFormat('j M Y')]) }}@if ($ct->next_done_at) · {{ __('done') }}@endif</div>@endif
                </div>
            @endforeach
        @endif
    </x-filament::section>

    @if ($alerts->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ __('Open alerts') }}</x-slot>
            @foreach ($alerts as $a)
                <div style="padding:6px 0;border-top:1px solid rgba(148,163,184,.2)">
                    <x-filament::badge :color="\App\Support\Severity::color($a->severity)" :icon="\App\Support\Severity::icon($a->severity)" style="display:inline-flex">{{ \App\Support\Severity::label($a->severity) }}</x-filament::badge>
                    <strong style="margin-inline-start:6px">{{ $a->localTitle() }}</strong>
                    <div style="{{ $muted }};margin-top:2px">{{ $a->localDetail() }}</div>
                </div>
            @endforeach
        </x-filament::section>
    @endif

    @if (count($funnel) > 1)
        <x-filament::section>
            <x-slot name="heading">{{ __('Funnel') }}</x-slot>
            @if ($funnelLocked ?? false)
                <x-locked-state-body feature="funnel" tier="pro" :can-upgrade="$canUpgrade" />
            @else
            @php $fTop = max(1, $funnel[0]['n']); @endphp
            <div style="display:grid;gap:8px">
                @foreach ($funnel as $f)
                    <div style="display:grid;grid-template-columns:150px 1fr 150px;gap:10px;align-items:center;font-size:13px">
                        <div>{{ __($f['label']) }}</div>
                        <div style="height:14px;border-radius:6px;background:rgba(148,163,184,.18);overflow:hidden">
                            <div style="height:14px;width:{{ max(1, round(log10(max(1, $f['n'])) / log10(max(10, $fTop)) * 100)) }}%;background:#14B8A6"></div>
                        </div>
                        <div style="text-align:end"><strong>{{ number_format($f['n']) }}</strong>@if ($f['rate'] !== null)<span style="{{ $muted }}"> · {{ number_format($f['rate'], $f['rate'] < 1 ? 2 : 1) }}%</span>@endif</div>
                    </div>
                @endforeach
            </div>
            <div style="{{ $muted }};margin-top:6px">{{ __('Bars use a log scale so small steps stay visible. % = share of the step before.') }}</div>
            @endif
        </x-filament::section>
    @endif

    @if (count($actions))
        <x-filament::section>
            <x-slot name="heading">{{ __('Google conversions by action') }}</x-slot>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Conversion action') }}</th><th style="padding:6px;text-align:start">{{ __('Category') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('Conversions') }}</th><th style="padding:6px;text-align:end">{{ __('Value') }}</th></tr></thead>
                <tbody>
                @foreach ($actions as $name => $a)
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px">{{ $name }}</td><td style="padding:6px">{{ $a['category'] ? ucfirst(strtolower(str_replace('_', ' ', $a['category']))) : '–' }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($a['conversions'], 1) }}</td>
                        <td style="padding:6px;text-align:end">{{ $a['value'] > 0 ? R::money($a['value']) : '–' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="{{ $muted }};margin-top:6px">{{ __('Count-only actions carry no value. Results above use primary conversion actions only.') }}</div>
        </x-filament::section>
    @endif

    {{-- FS-24 / XS-44 (R4): daily spend; a day without data is a gap with its reason, never a zero. --}}
    @php
        $sd = $series['days'];
        $maxDay = max(1, collect($sd)->max('spend') ?? 0);
        $gapColor = ['not_connected' => 'rgba(148,163,184,.35)', 'sync_failed' => 'rgba(180,35,24,.35)', 'no_data' => 'rgba(181,71,8,.30)'];
        $gapLabels = \App\Support\DataGaps::reasonLabels();
    @endphp
    <x-filament::section data-daily-chart>
        <x-slot name="heading">{{ __('Daily spend') }}</x-slot>
        <div role="img" aria-label="{{ __('Daily spend for the period; days without data are marked as gaps') }}" style="display:flex;gap:2px;align-items:flex-end;height:120px;overflow-x:auto">
            @foreach ($sd as $day => $v)
                @if ($v['gap'])
                    <div data-gap="{{ $v['gap'] }}" title="{{ $day }} · {{ __('No data') }}: {{ $gapLabels[$v['gap']] }}" style="flex:1 0 6px;min-width:6px;height:100%;border-radius:3px;background:repeating-linear-gradient(135deg,{{ $gapColor[$v['gap']] }} 0 3px,transparent 3px 6px)"></div>
                @else
                    <div title="{{ $day }} · {{ R::money($v['spend'], $cur) }}" style="flex:1 0 6px;min-width:6px;border-radius:3px 3px 0 0;background:#14B8A6;height:{{ max(2, round($v['spend'] / $maxDay * 118)) }}px"></div>
                @endif
            @endforeach
        </div>
        @if ($series['gaps'])
            <ul style="margin:8px 0 0;padding-inline-start:18px;font-size:13px" data-gap-list>
                @foreach ($series['gaps'] as $g)
                    <li>{{ trans_choice('No data for :n day|No data for :n days', $g['days'], ['n' => $g['days']]) }} (<bdi dir="ltr">{{ $g['from'] === $g['to'] ? $g['from'] : $g['from'].' → '.$g['to'] }}</bdi>): {{ $gapLabels[$g['reason']] }}</li>
                @endforeach
            </ul>
        @else
            <div style="{{ $muted }};margin-top:6px">{{ __('Data for every day in this period.') }}</div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('6-month trend') }}</x-slot>
        <div style="display:grid;grid-template-columns:repeat({{ count($months) }},1fr);gap:10px;align-items:end;height:170px">
            @foreach ($months as $m => $v)
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end" title="{{ $m }}: {{ R::money($v['spend'], $cur) }} · {{ trans_choice(':count result|:count results', (int) $v['main_results'], ['count' => number_format($v['main_results'])]) }}">
                    <div style="display:flex;gap:3px;align-items:flex-end;height:130px">
                        <div style="width:14px;border-radius:4px 4px 0 0;background:#14B8A6;height:{{ max(2, round($v['spend'] / $maxSpend * 130)) }}px"></div>
                        <div style="width:14px;border-radius:4px 4px 0 0;background:#6366f1;height:{{ max(2, round($v['main_results'] / $maxRes * 130)) }}px"></div>
                    </div>
                    <div style="{{ $muted }}">{{ \Carbon\Carbon::parse($m.'-01')->translatedFormat('M') }}</div>
                </div>
            @endforeach
        </div>
        <div style="{{ $muted }};margin-top:6px"><span style="color:#14B8A6">■</span> {{ __('Spend') }} · <span style="color:#6366f1">■</span> {{ __('Results (main goal each month)') }}</div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            <span wire:click="openCampaign(null)" style="cursor:pointer">{{ __('Campaigns') }}</span>
            @if ($crumbs['campaign']) › <span wire:click="openAdSet(null)" style="cursor:pointer">{{ $crumbs['campaign'] }}</span> @endif
            @if ($crumbs['ad_set']) › {{ $crumbs['ad_set'] }} @endif
        </x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="text-align:start;{{ $muted }}">
                    <th style="padding:6px;text-align:start">{{ __(['campaign' => 'Campaign', 'ad_set' => 'Ad set', 'ad' => 'Ad'][$level]) }}</th>
                    <th style="padding:6px;text-align:start">{{ __('Goal') }}</th><th style="padding:6px;text-align:end">{{ __('Spend') }}</th><th style="padding:6px;text-align:end">{{ __('Results') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('Cost / result') }}</th><th style="padding:6px;text-align:end">{{ __('Impr.') }}</th><th style="padding:6px;text-align:end">{{ __('CTR') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px">
                            @if ($level === 'campaign')
                                <a wire:click="openCampaign({{ $r['ref'] }})" style="cursor:pointer;color:#14B8A6;font-weight:600">{{ $r['name'] }}</a>
                            @elseif ($level === 'ad_set')
                                <a wire:click="openAdSet({{ $r['ref'] }})" style="cursor:pointer;color:#14B8A6;font-weight:600">{{ $r['name'] }}</a>
                            @else
                                <span style="display:inline-flex;gap:8px;align-items:center">
                                    @if ($r['thumbnail'])<img src="{{ $r['thumbnail'] }}" alt="" style="width:32px;height:32px;border-radius:6px;object-fit:cover">@endif
                                    <span style="font-weight:600">{{ $r['name'] }}</span>
                                </span>
                            @endif
                            @if ($r['status'])<span style="{{ $muted }}"> · {{ strtolower($r['status']) }}</span>@endif
                        </td>
                        <td style="padding:6px">{{ R::label($r['main_type'], app()->getLocale()) }}</td>
                        <td style="padding:6px;text-align:end">{{ R::money($r['spend']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($r['main_results']) }}</td>
                        <td style="padding:6px;text-align:end">{{ R::money($r['cpr']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($r['impressions']) }}</td>
                        <td style="padding:6px;text-align:end">{{ $r['ctr'] ? number_format($r['ctr'], 2).'%' : '–' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:10px;{{ $muted }}">{{ __('No data for this period yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if (count($top))
        <x-filament::section>
            <x-slot name="heading">{{ __('Top ads') }}</x-slot>
            <x-slot name="afterHeader"><a href="{{ \App\Filament\Pages\TopAdsPage::getUrl(['client' => $client->id]) }}" style="display:inline-flex;align-items:center;min-height:44px;color:var(--primary-600);font-size:13px">{{ __('See all ads') }}</a></x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px">
                @foreach ($top as $ad)
                    <div style="{{ $card }};padding:8px">
                        @if ($ad['thumbnail'])
                            <img src="{{ $ad['thumbnail'] }}" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px">
                        @else
                            <div style="width:100%;aspect-ratio:1;border-radius:8px;background:rgba(148,163,184,.15)"></div>
                        @endif
                        <div style="font-size:12px;font-weight:600;margin-top:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $ad['name'] }}</div>
                        <div style="{{ $muted }}">{{ number_format($ad['main_results']) }} {{ R::label($ad['main_type'], app()->getLocale()) }} · {{ R::money($ad['cpr'], $cur) }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
    @if ($forecastLocked)
        <x-locked-modal feature="forecast" :can-upgrade="$canUpgrade" />
    @endif
</x-filament-panels::page>
