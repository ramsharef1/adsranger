@php
    use App\Support\ResultResolver as R;
    $muted = 'opacity:.65;font-size:12px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px';
    $field = 'padding:5px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $pill = fn ($on) => 'padding:5px 12px;border-radius:99px;font-size:13px;cursor:pointer;border:1px solid '.($on ? '#14B8A6' : 'rgba(148,163,184,.35)').';background:'.($on ? 'rgba(20,184,166,.12)' : 'transparent');
    $cur = $client?->currency;
    $th = 'padding:6px;text-align:end';
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $noData = 'No breakdown data yet for this period. It fills in after the next sync of a Meta or Google Ads account (hours, placements, regions / cities, age and gender).';
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <label style="{{ $muted }}" for="in-client">Client</label>
            <select id="in-client" wire:model.live="clientId" style="{{ $field }}">
                @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            @foreach ($periods as $k => $label)
                <button type="button" wire:click="setPeriod('{{ $k }}')" style="{{ $pill($period === $k) }}">{{ $label }}</button>
            @endforeach
        </div>
        @if ($client)<div style="{{ $muted }}">{{ $from }} → {{ $to }} · {{ $cur }}</div>@endif
    </div>

    @if (! $client)
        <x-filament::section><div style="{{ $muted }}">No active clients yet. Add a client and link its ad accounts to see insights.</div></x-filament::section>
    @else
        {{-- #38 Budget planner --}}
        <x-filament::section>
            <x-slot name="heading">Budget planner: what more or less budget would bring</x-slot>
            @if (! $curve)
                <div style="{{ $muted }}">Needs at least {{ config('insights.curve.min_points') }} days with spend and results in the last {{ config('insights.curve.days') }} days.</div>
            @else
                @php
                    $maxS = max(array_column($curve['scatter'], 0)) * 2.1; $maxR = max(max(array_column($curve['scatter'], 1)), ($curve['fit'])($maxS)) * 1.1;
                    $W = 520; $H = 200; $px = fn ($s) => 40 + $s / $maxS * ($W - 50); $py = fn ($r) => $H - 24 - $r / $maxR * ($H - 34);
                    $line = collect(range(1, 40))->map(fn ($i) => round($px($maxS * $i / 40), 1).','.round($py(($curve['fit'])($maxS * $i / 40)), 1))->implode(' ');
                @endphp
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;align-items:start">
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Daily spend against daily {{ strtolower(R::label($curve['main_type'])) }}, with the fitted curve" style="width:100%;height:auto">
                        <line x1="40" y1="{{ $H - 24 }}" x2="{{ $W - 10 }}" y2="{{ $H - 24 }}" stroke="currentColor" stroke-opacity=".25"/>
                        <line x1="40" y1="10" x2="40" y2="{{ $H - 24 }}" stroke="currentColor" stroke-opacity=".25"/>
                        @foreach ($curve['scatter'] as [$s, $r])<circle cx="{{ round($px($s), 1) }}" cy="{{ round($py($r), 1) }}" r="3" fill="#14B8A6" fill-opacity=".45"/>@endforeach
                        <polyline points="{{ $line }}" fill="none" stroke="#0f766e" stroke-width="2"/>
                        <text x="{{ $W - 10 }}" y="{{ $H - 6 }}" text-anchor="end" font-size="11" fill="currentColor" fill-opacity=".6">Daily spend ({{ $cur }})</text>
                        <text x="44" y="18" font-size="11" fill="currentColor" fill-opacity=".6">{{ R::label($curve['main_type']) }} / day</text>
                    </svg>
                    <div>
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">Daily budget</th><th style="{{ $th }}">{{ R::label($curve['main_type']) }} / month</th>
                                <th style="{{ $th }}">Cost / result</th><th style="{{ $th }}" title="Cost of the last extra result at this budget">Extra result costs</th></tr></thead>
                            <tbody>
                            @foreach ($curve['steps'] as $s)
                                <tr style="border-top:1px solid rgba(148,163,184,.2);{{ $s['mult'] == 1.0 ? 'font-weight:700' : '' }}">
                                    <td style="padding:6px">{{ R::money($s['spend'], $cur) }} <span style="{{ $muted }}">{{ $s['mult'] == 1.0 ? 'now' : ($s['mult'] > 1 ? '+' : '−').abs(round(($s['mult'] - 1) * 100)).'%' }}</span></td>
                                    <td style="{{ $th }}">{{ number_format($s['month_results']) }}</td>
                                    <td style="{{ $th }}">{{ R::money($s['cpr'], $cur) }}</td>
                                    <td style="{{ $th }}">{{ R::money($s['marginal_cpr'], $cur) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div style="{{ $muted }};margin-top:8px">
                            Fitted on {{ $curve['points'] }} days: results grow with spend^{{ number_format($curve['b'], 2) }}
                            ({{ $curve['b'] < 0.6 ? 'strong' : ($curve['b'] < 0.9 ? 'some' : 'little') }} diminishing returns).
                            Fit quality: <strong>{{ $curve['confidence'] }}</strong> (R² {{ number_format($curve['r2'], 2) }}). An estimate from history, not a promise; seasonality and creative changes move it.
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>

        {{-- #39 Reallocation --}}
        <x-filament::section>
            <x-slot name="heading">Budget reallocation</x-slot>
            @if ($realloc['suggestion'])
                @php $sg = $realloc['suggestion']; @endphp
                <div style="{{ $card }};border-color:#14B8A6">
                    Move about <strong>{{ R::money($sg['amount'], $cur) }} a day</strong> from <strong>{{ $sg['from'] }}</strong> to <strong>{{ $sg['to'] }}</strong>:
                    about <strong>{{ number_format($sg['results_after'] - $sg['results_before']) }} more results a month</strong> (+{{ number_format($sg['gain_pct'], 1) }}%) for the same spend.
                </div>
            @else
                <div style="{{ $muted }}">{{ $realloc['reason'] }}</div>
            @endif
            @if (count($realloc['campaigns']))
                <table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:10px">
                    <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">Campaign</th><th style="{{ $th }}">Daily spend (14 days)</th>
                        <th style="{{ $th }}">Results / day</th><th style="{{ $th }}">Extra result costs</th></tr></thead>
                    <tbody>
                    @foreach ($realloc['campaigns'] as $f)
                        <tr style="border-top:1px solid rgba(148,163,184,.2)"><td style="padding:6px">{{ $f['name'] }}</td><td style="{{ $th }}">{{ R::money($f['daily'], $cur) }}</td>
                            <td style="{{ $th }}">{{ number_format($f['results'], 1) }}</td><td style="{{ $th }}">{{ R::money($f['marginal'], $cur) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            <div style="{{ $muted }};margin-top:6px">Compares campaigns with the same main result. Review targeting and learning-phase limits before moving budget.</div>
        </x-filament::section>

        {{-- #43 Heatmap --}}
        <x-filament::section>
            <x-slot name="heading">Best and weakest hours</x-slot>
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
                    <button type="button" wire:click="setHeat('results')" style="{{ $pill($heat === 'results') }}">Results</button>
                    <button type="button" wire:click="setHeat('cpr')" style="{{ $pill($heat === 'cpr') }}">Cost per result (darker = cheaper)</button>
                    <button type="button" wire:click="setHeat('spend')" style="{{ $pill($heat === 'spend') }}">Spend</button>
                </div>
                <div style="overflow-x:auto">
                    <table style="border-collapse:separate;border-spacing:2px;font-size:11px">
                        <tr><td></td>@for ($h = 0; $h < 24; $h++)<td style="text-align:center;{{ $muted }};font-size:10px">{{ $h }}</td>@endfor</tr>
                        @foreach ([0, 1, 2, 3, 4, 5, 6] as $d)
                            <tr><td style="padding-inline-end:6px;{{ $muted }}">{{ $days[$d] }}</td>
                                @for ($h = 0; $h < 24; $h++)
                                    @php $cell = $g[$d][$h] ?? null; $t = $cell ? number_format($cell['results'], 1).' results · '.R::money($cell['spend'], $cur).' · CPR '.R::money($cell['cpr'], $cur) : 'no data'; @endphp
                                    <td title="{{ $days[$d] }} {{ $h }}:00 — {{ $t }}" aria-label="{{ $days[$d] }} {{ $h }}:00, {{ $t }}"
                                        style="width:22px;height:20px;border-radius:4px;background:rgba(20,184,166,{{ round(0.08 + 0.92 * $alpha($cell), 2) }})"></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div style="font-size:13px;margin-top:8px">
                    @if ($heatmap['best'])Cheapest hours: <strong>{{ collect($heatmap['best'])->map(fn ($h) => sprintf('%02d:00', $h))->implode(', ') }}</strong> ·
                        most expensive: <strong>{{ collect($heatmap['worst'])->map(fn ($h) => sprintf('%02d:00', $h))->implode(', ') }}</strong>.@endif
                    <span style="{{ $muted }}">Account time zone. Hours need 3+ results to be ranked.</span>
                </div>
            @endif
        </x-filament::section>

        {{-- #44 Placements + devices --}}
        @foreach (['Placements' => $placements, 'Devices (Google)' => $devices, 'Age and gender (Meta)' => $audience, 'Regions and cities' => $geo] as $title => $b)
            @if ($title === 'Devices (Google)' && ! $b['has']) @continue @endif
            <x-filament::section>
                <x-slot name="heading">{{ $title }}</x-slot>
                @if (! $b['has'])
                    <div style="{{ $muted }}">{{ $noData }}</div>
                @else
                    @php $rows = array_slice($b['rows'], 0, $title === 'Regions and cities' ? 25 : 20); $maxShare = max(1, max(array_column($rows, 'share'))); @endphp
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ $title === 'Regions and cities' ? 'Location' : 'Name' }}</th>
                                <th style="padding:6px;text-align:start;min-width:120px">Share of spend</th><th style="{{ $th }}">Spend</th>
                                <th style="{{ $th }}">{{ R::label($b['main_type']) ?: 'Results' }}</th><th style="{{ $th }}">Cost / result</th>
                                <th style="{{ $th }}">Link CTR</th><th style="{{ $th }}">CPM</th></tr></thead>
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
                    @if ($title === 'Regions and cities')<div style="{{ $muted }};margin-top:6px">Meta reports regions; Google Ads reports cities. Top 25 by spend.</div>@endif
                @endif
            </x-filament::section>
        @endforeach

        {{-- #98 Saturation + overlap --}}
        <x-filament::section>
            <x-slot name="heading">Audience saturation and overlap (Meta, last 30 days)</x-slot>
            @if (! $saturation['has'])
                <div style="{{ $muted }}">No 30-day reach yet. It is pulled with the nightly sync of Meta accounts.</div>
            @else
                @if ($saturation['overlap'] !== null)
                    <div style="{{ $card }};margin-bottom:10px;{{ $saturation['overlap'] >= config('insights.overlap_warn_pct') ? 'border-color:#f59e0b' : '' }}">
                        About <strong>{{ number_format($saturation['overlap'], 0) }}%</strong> of the people reached by these ad sets were reached by more than one of them
                        ({{ number_format($saturation['account_reach']) }} people in total vs {{ number_format($saturation['sum_reach']) }} counted per ad set).
                        @if ($saturation['overlap'] >= config('insights.overlap_warn_pct')) Consider merging overlapping ad sets or excluding audiences. @endif
                    </div>
                @endif
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">Ad set</th><th style="{{ $th }}">Reach</th><th style="{{ $th }}">Frequency</th>
                        <th style="{{ $th }}">CTR now vs 3 weeks ago</th><th style="padding:6px;text-align:start">State</th></tr></thead>
                    <tbody>
                    @foreach ($saturation['rows'] as $r)
                        <tr style="border-top:1px solid rgba(148,163,184,.2)">
                            <td style="padding:6px">{{ $r['name'] }}</td><td style="{{ $th }}">{{ number_format($r['reach']) }}</td>
                            <td style="{{ $th }}">{{ number_format($r['frequency'], 2) }}</td>
                            <td style="{{ $th }}">{{ $r['ctr_now'] !== null ? number_format($r['ctr_now'], 2).'%' : '–' }} · {{ $r['ctr_then'] !== null ? number_format($r['ctr_then'], 2).'%' : '–' }}
                                @if ($r['ctr_drop'] !== null)<span style="{{ $muted }}"> ({{ $r['ctr_drop'] >= 0 ? '−' : '+' }}{{ number_format(abs($r['ctr_drop']), 0) }}%)</span>@endif</td>
                            <td style="padding:6px"><x-filament::badge :color="['saturating' => 'danger', 'high_frequency' => 'warning', 'ok' => 'success'][$r['state']]">{{ ['saturating' => 'Saturating: refresh creative or widen audience', 'high_frequency' => 'High frequency', 'ok' => 'OK'][$r['state']] }}</x-filament::badge></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div style="{{ $muted }};margin-top:6px">Saturating = frequency {{ config('insights.saturation.frequency') }}+ and link CTR down {{ config('insights.saturation.ctr_drop_pct') }}%+. Overlap is an estimate from reach (Meta's own overlap tool is not available through the API). As of {{ $saturation['as_of'] }}.</div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
