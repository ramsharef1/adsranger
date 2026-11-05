@php
    use App\Support\ResultResolver as R;
    $muted = 'opacity:.65;font-size:12px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px';
    $field = 'padding:5px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $pill = fn ($on) => 'padding:5px 12px;border-radius:99px;font-size:13px;cursor:pointer;border:1px solid '.($on ? '#14B8A6' : 'rgba(148,163,184,.35)').';background:'.($on ? 'rgba(20,184,166,.12)' : 'transparent');
    $cur = $client?->currency;
    $th = 'padding:6px;text-align:end';
    $days = [__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')];
    $noData = __('No breakdown data yet for this period. It fills in after the next sync of a Meta or Google Ads account (hours, placements, regions / cities, age and gender).');
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <label style="{{ $muted }}" for="in-client">{{ __('Client') }}</label>
            <select id="in-client" wire:model.live="clientId" style="{{ $field }}">
                @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            @foreach ($periods as $k => $label)
                <button type="button" wire:click="setPeriod('{{ $k }}')" style="{{ $pill($period === $k) }}">{{ __($label) }}</button>
            @endforeach
        </div>
        @if ($client)<div style="{{ $muted }}">{{ $from }} → {{ $to }} · {{ $cur }}</div>@endif
    </div>

    @if (! $client)
        <x-filament::section><div style="{{ $muted }}">{{ __('No active clients yet. Add a client and link its ad accounts to see insights.') }}</div></x-filament::section>
    @else
        {{-- #38 Budget planner --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Budget planner: what more or less budget would bring') }}</x-slot>
            @if (! $curve)
                <div style="{{ $muted }}">{{ __('Needs at least :points days with spend and results in the last :days days.', ['points' => config('insights.curve.min_points'), 'days' => config('insights.curve.days')]) }}</div>
            @else
                @php
                    $maxS = max(array_column($curve['scatter'], 0)) * 2.1; $maxR = max(max(array_column($curve['scatter'], 1)), ($curve['fit'])($maxS)) * 1.1;
                    $W = 520; $H = 200; $px = fn ($s) => 40 + $s / $maxS * ($W - 50); $py = fn ($r) => $H - 24 - $r / $maxR * ($H - 34);
                    $line = collect(range(1, 40))->map(fn ($i) => round($px($maxS * $i / 40), 1).','.round($py(($curve['fit'])($maxS * $i / 40)), 1))->implode(' ');
                @endphp
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;align-items:start">
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ __('Daily spend against daily :result, with the fitted curve', ['result' => strtolower(R::label($curve['main_type'], app()->getLocale()))]) }}" style="width:100%;height:auto">
                        <line x1="40" y1="{{ $H - 24 }}" x2="{{ $W - 10 }}" y2="{{ $H - 24 }}" stroke="currentColor" stroke-opacity=".25"/>
                        <line x1="40" y1="10" x2="40" y2="{{ $H - 24 }}" stroke="currentColor" stroke-opacity=".25"/>
                        @foreach ($curve['scatter'] as [$s, $r])<circle cx="{{ round($px($s), 1) }}" cy="{{ round($py($r), 1) }}" r="3" fill="#14B8A6" fill-opacity=".45"/>@endforeach
                        <polyline points="{{ $line }}" fill="none" stroke="#0f766e" stroke-width="2"/>
                        <text x="{{ $W - 10 }}" y="{{ $H - 6 }}" text-anchor="end" font-size="11" fill="currentColor" fill-opacity=".6">{{ __('Daily spend (:currency)', ['currency' => $cur]) }}</text>
                        <text x="44" y="18" font-size="11" fill="currentColor" fill-opacity=".6">{{ __(':result / day', ['result' => R::label($curve['main_type'], app()->getLocale())]) }}</text>
                    </svg>
                    <div>
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Daily budget') }}</th><th style="{{ $th }}">{{ __(':result / month', ['result' => R::label($curve['main_type'], app()->getLocale())]) }}</th>
                                <th style="{{ $th }}">{{ __('Cost / result') }}</th><th style="{{ $th }}" title="{{ __('Cost of the last extra result at this budget') }}">{{ __('Extra result costs') }}</th></tr></thead>
                            <tbody>
                            @foreach ($curve['steps'] as $s)
                                <tr style="border-top:1px solid rgba(148,163,184,.2);{{ $s['mult'] == 1.0 ? 'font-weight:700' : '' }}">
                                    <td style="padding:6px">{{ R::money($s['spend'], $cur) }} <span style="{{ $muted }}">{{ $s['mult'] == 1.0 ? __('now') : ($s['mult'] > 1 ? '+' : '−').abs(round(($s['mult'] - 1) * 100)).'%' }}</span></td>
                                    <td style="{{ $th }}">{{ number_format($s['month_results']) }}</td>
                                    <td style="{{ $th }}">{{ R::money($s['cpr'], $cur) }}</td>
                                    <td style="{{ $th }}">{{ R::money($s['marginal_cpr'], $cur) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div style="{{ $muted }};margin-top:8px">
                            {{ __('Fitted on :points days: results grow with spend^:b (:strength diminishing returns).', ['points' => $curve['points'], 'b' => number_format($curve['b'], 2), 'strength' => $curve['b'] < 0.6 ? __('strong') : ($curve['b'] < 0.9 ? __('some') : __('little'))]) }}
                            {!! __('Fit quality: :quality (R² :r2).', ['quality' => '<strong>'.e(__($curve['confidence'])).'</strong>', 'r2' => e(number_format($curve['r2'], 2))]) !!}
                            {{ __('An estimate from history, not a promise; seasonality and creative changes move it.') }}
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>

        {{-- #39 Reallocation --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Budget reallocation') }}</x-slot>
            @if ($realloc['suggestion'])
                @php $sg = $realloc['suggestion']; @endphp
                <div style="{{ $card }};border-color:#14B8A6">
                    {!! __('Move about :amount a day from :from to :to: about :more more results a month (+:gain%) for the same spend.', ['amount' => '<strong><bdi>'.e(R::money($sg['amount'], $cur)).'</bdi></strong>', 'from' => '<strong><bdi>'.e($sg['from']).'</bdi></strong>', 'to' => '<strong><bdi>'.e($sg['to']).'</bdi></strong>', 'more' => '<strong>'.e(number_format($sg['results_after'] - $sg['results_before'])).'</strong>', 'gain' => e(number_format($sg['gain_pct'], 1))]) !!}
                </div>
            @else
                <div style="{{ $muted }}">{{ __($realloc['reason']) }}</div>
            @endif
            @if (count($realloc['campaigns']))
                <table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:10px">
                    <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Campaign') }}</th><th style="{{ $th }}">{{ __('Daily spend (14 days)') }}</th>
                        <th style="{{ $th }}">{{ __('Results / day') }}</th><th style="{{ $th }}">{{ __('Extra result costs') }}</th></tr></thead>
                    <tbody>
                    @foreach ($realloc['campaigns'] as $f)
                        <tr style="border-top:1px solid rgba(148,163,184,.2)"><td style="padding:6px">{{ $f['name'] }}</td><td style="{{ $th }}">{{ R::money($f['daily'], $cur) }}</td>
                            <td style="{{ $th }}">{{ number_format($f['results'], 1) }}</td><td style="{{ $th }}">{{ R::money($f['marginal'], $cur) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            <div style="{{ $muted }};margin-top:6px">{{ __('Compares campaigns with the same main result. Review targeting and learning-phase limits before moving budget.') }}</div>
        </x-filament::section>

        {{-- #43 Heatmap --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Best and weakest hours') }}</x-slot>
            @if (! $heatmap['has'])
                <div style="{{ $muted }}">{{ $noData }}</div>
            @else
                @php
                    $g = $heatmap['grid'];
                    $cprs = collect($g)->flatten(1)->pluck('cpr')->filter();
                    $minCpr = $cprs->min(); $maxSpend = collect($g)->flatten(1)->max('spend') ?: 1;
                    $alpha = function ($cell) use ($heat, $heatmap, $minCpr, $maxSpend) {
                        if (! $cell) return 0;
                        return match ($heat) {
                            'cpr' => $cell['cpr'] ? min(1, $minCpr / $cell['cpr']) : 0,
                            'spend' => $cell['spend'] / $maxSpend,
                            default => $heatmap['max_results'] ? $cell['results'] / $heatmap['max_results'] : 0,
                        };
                    };
                @endphp
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">
                    <button type="button" wire:click="setHeat('results')" style="{{ $pill($heat === 'results') }}">{{ __('Results') }}</button>
                    <button type="button" wire:click="setHeat('cpr')" style="{{ $pill($heat === 'cpr') }}">{{ __('Cost per result (darker = cheaper)') }}</button>
                    <button type="button" wire:click="setHeat('spend')" style="{{ $pill($heat === 'spend') }}">{{ __('Spend') }}</button>
                </div>
                <div style="overflow-x:auto">
                    <table style="border-collapse:separate;border-spacing:2px;font-size:11px">
                        <tr><td></td>@for ($h = 0; $h < 24; $h++)<td style="text-align:center;{{ $muted }};font-size:10px">{{ $h }}</td>@endfor</tr>
                        @foreach ([0, 1, 2, 3, 4, 5, 6] as $d)
                            <tr><td style="padding-inline-end:6px;{{ $muted }}">{{ $days[$d] }}</td>
                                @for ($h = 0; $h < 24; $h++)
                                    @php $cell = $g[$d][$h] ?? null; $t = $cell ? __(':results results · :spend · CPR :cpr', ['results' => number_format($cell['results'], 1), 'spend' => R::money($cell['spend'], $cur), 'cpr' => R::money($cell['cpr'], $cur)]) : __('no data'); @endphp
                                    <td title="{{ $days[$d] }} {{ $h }}:00 — {{ $t }}" aria-label="{{ $days[$d] }} {{ $h }}:00, {{ $t }}"
                                        style="width:22px;height:20px;border-radius:4px;background:rgba(20,184,166,{{ round(0.08 + 0.92 * $alpha($cell), 2) }})"></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div style="font-size:13px;margin-top:8px">
                    @if ($heatmap['best']){{ __('Cheapest hours:') }} <strong>{{ collect($heatmap['best'])->map(fn ($h) => sprintf('%02d:00', $h))->implode(', ') }}</strong> {{ __('· most expensive:') }} <strong>{{ collect($heatmap['worst'])->map(fn ($h) => sprintf('%02d:00', $h))->implode(', ') }}</strong>.@endif
                    <span style="{{ $muted }}">{{ __('Account time zone. Hours need 3+ results to be ranked.') }}</span>
                </div>
            @endif
        </x-filament::section>

        {{-- #44 Placements + devices --}}
        @foreach (['Placements' => $placements, 'Devices (Google)' => $devices, 'Age and gender (Meta)' => $audience, 'Regions and cities' => $geo] as $title => $b)
            @if ($title === 'Devices (Google)' && ! $b['has']) @continue @endif
            <x-filament::section>
                <x-slot name="heading">{{ __($title) }}</x-slot>
                @if (! $b['has'])
                    <div style="{{ $muted }}">{{ $noData }}</div>
                @else
                    @php $rows = array_slice($b['rows'], 0, $title === 'Regions and cities' ? 25 : 20); $maxShare = max(1, max(array_column($rows, 'share'))); @endphp
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ $title === 'Regions and cities' ? __('Location') : __('Name') }}</th>
                                <th style="padding:6px;text-align:start;min-width:120px">{{ __('Share of spend') }}</th><th style="{{ $th }}">{{ __('Spend') }}</th>
                                <th style="{{ $th }}">{{ R::label($b['main_type'], app()->getLocale()) ?: __('Results') }}</th><th style="{{ $th }}">{{ __('Cost / result') }}</th>
                                <th style="{{ $th }}">{{ __('Link CTR') }}</th><th style="{{ $th }}">{{ __('CPM') }}</th></tr></thead>
                            <tbody>
                            @foreach ($rows as $r)
                                <tr style="border-top:1px solid rgba(148,163,184,.2)">
                                    <td style="padding:6px">{{ $r['name'] }}</td>
                                    <td style="padding:6px"><div style="display:flex;align-items:center;gap:6px"><div style="height:8px;border-radius:4px;background:#14B8A6;width:{{ max(2, round($r['share'] / $maxShare * 100)) }}px"></div><span style="{{ $muted }}">{{ number_format($r['share'], 1) }}%</span></div></td>
                                    <td style="{{ $th }}">{{ R::money($r['spend'], $cur) }}</td>
                                    <td style="{{ $th }}">{{ number_format($r['results']) }}</td>
                                    <td style="{{ $th }}">{{ R::money($r['cpr'], $cur) }}</td>
                                    <td style="{{ $th }}">{{ $r['ctr'] !== null ? number_format($r['ctr'], 2).'%' : '–' }}</td>
                                    <td style="{{ $th }}">{{ R::money($r['cpm'], $cur) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($title === 'Regions and cities')<div style="{{ $muted }};margin-top:6px">{{ __('Meta reports regions; Google Ads reports cities. Top 25 by spend.') }}</div>@endif
                @endif
            </x-filament::section>
        @endforeach

        {{-- #98 Saturation + overlap --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Audience saturation and overlap (Meta, last 30 days)') }}</x-slot>
            @if (! $saturation['has'])
                <div style="{{ $muted }}">{{ __('No 30-day reach yet. It is pulled with the nightly sync of Meta accounts.') }}</div>
            @else
                @if ($saturation['overlap'] !== null)
                    <div style="{{ $card }};margin-bottom:10px;{{ $saturation['overlap'] >= config('insights.overlap_warn_pct') ? 'border-color:#f59e0b' : '' }}">
                        {!! __('About :pct of the people reached by these ad sets were reached by more than one of them (:total people in total vs :sum counted per ad set).', ['pct' => '<strong>'.e(number_format($saturation['overlap'], 0)).'%</strong>', 'total' => e(number_format($saturation['account_reach'])), 'sum' => e(number_format($saturation['sum_reach']))]) !!}
                        @if ($saturation['overlap'] >= config('insights.overlap_warn_pct')) {{ __('Consider merging overlapping ad sets or excluding audiences.') }} @endif
                    </div>
                @endif
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Ad set') }}</th><th style="{{ $th }}">{{ __('Reach') }}</th><th style="{{ $th }}">{{ __('Frequency') }}</th>
                        <th style="{{ $th }}">{{ __('CTR now vs 3 weeks ago') }}</th><th style="padding:6px;text-align:start">{{ __('State') }}</th></tr></thead>
                    <tbody>
                    @foreach ($saturation['rows'] as $r)
                        <tr style="border-top:1px solid rgba(148,163,184,.2)">
                            <td style="padding:6px">{{ $r['name'] }}</td><td style="{{ $th }}">{{ number_format($r['reach']) }}</td>
                            <td style="{{ $th }}">{{ number_format($r['frequency'], 2) }}</td>
                            <td style="{{ $th }}">{{ $r['ctr_now'] !== null ? number_format($r['ctr_now'], 2).'%' : '–' }} · {{ $r['ctr_then'] !== null ? number_format($r['ctr_then'], 2).'%' : '–' }}
                                @if ($r['ctr_drop'] !== null)<span style="{{ $muted }}"> ({{ $r['ctr_drop'] >= 0 ? '−' : '+' }}{{ number_format(abs($r['ctr_drop']), 0) }}%)</span>@endif</td>
                            <td style="padding:6px"><x-filament::badge :color="['saturating' => 'danger', 'high_frequency' => 'warning', 'ok' => 'success'][$r['state']]">{{ __(['saturating' => 'Saturating: refresh creative or widen audience', 'high_frequency' => 'High frequency', 'ok' => 'OK'][$r['state']]) }}</x-filament::badge></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div style="{{ $muted }};margin-top:6px">{{ __('Saturating = frequency :frequency+ and link CTR down :drop%+. Overlap is an estimate from reach (Meta\'s own overlap tool is not available through the API). As of :date.', ['frequency' => config('insights.saturation.frequency'), 'drop' => config('insights.saturation.ctr_drop_pct'), 'date' => $saturation['as_of']]) }}</div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
