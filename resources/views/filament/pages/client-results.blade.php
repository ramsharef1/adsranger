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
        ['Spend', R::money($t['spend'], $cur), $delta($t['spend'], $prev['spend']), null],
        [R::label($t['main_type']) ?: 'Results', number_format($t['main_results']), $delta($t['main_results'], $prev['main_results']), 'Main goal by spend'],
        ['Cost per result', R::money($t['cpr'], $cur), $delta($t['cpr'], $prev['cpr'], true), null],
        ['ROAS', $t['roas'] ? number_format($t['roas'], 2).'×' : '–', $delta($t['roas'], $prev['roas']), 'Purchase / conversion goals only'],
        ['Link CTR', $t['ctr'] ? number_format($t['ctr'], 2).'%' : '–', $delta($t['ctr'], $prev['ctr']), null],
    ];
    $goals = array_filter([
        'Leads' => $t['leads'], 'Conversations' => $t['messages'], 'Calls placed' => $t['calls'], 'Calls confirmed' => $t['calls_confirmed'],
        'Calls 20s+' => $t['calls_20s'], 'Purchases' => $t['purchases'], 'Purchase value' => $t['revenue'] > 0 ? R::money($t['revenue'], $cur) : 0,
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
                <button type="button" wire:click="setPeriod('{{ $k }}')" style="{{ $pill($period === $k) }}">{{ $label }}</button>
            @endforeach
        </div>
        <div style="{{ $muted }}">
            {{ $from }} → {{ $to }} · {{ $cur }} ·
            @if ($synced) Data updated {{ $synced->diffForHumans() }} @else No sync yet @endif
            @if ($accounts->isEmpty()) · <a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('edit', ['record' => $client]) }}" style="color:#14B8A6">link an ad account</a> @endif
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
                        <span style="opacity:.6">vs previous</span>
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
                <div style="{{ $muted }}">Health score (last 14 days)</div>
                <x-filament::badge :color="$health['color']">{{ $health['band'] }}</x-filament::badge>
            </div>
            <div style="font-size:28px;font-weight:700;margin:4px 0 8px">{{ $health['score'] ?? '–' }}<span style="{{ $muted }}"> / 100</span></div>
            @forelse ($health['parts'] as $k => $part)
                <div style="display:grid;grid-template-columns:90px 1fr 54px;gap:8px;align-items:center;font-size:12px;margin-top:4px">
                    <div>{{ ['results' => 'Results', 'pacing' => 'Pacing', 'trend' => 'Trend', 'tracking' => 'Tracking'][$k] ?? $k }}</div>
                    <div style="height:8px;border-radius:6px;background:rgba(148,163,184,.18);overflow:hidden" role="img" aria-label="{{ $part['points'] }} of {{ $part['max'] }}">
                        <div style="height:8px;width:{{ $part['max'] ? round($part['points'] / $part['max'] * 100) : 0 }}%;background:#14B8A6"></div>
                    </div>
                    <div style="text-align:end">{{ $part['points'] }}/{{ $part['max'] }}</div>
                </div>
                <div style="{{ $muted }};margin:1px 0 0 98px">{{ $part['note'] }}</div>
            @empty
                <div style="{{ $muted }}">No spend or results in the last 14 days.</div>
            @endforelse
        </div>
        <div style="{{ $card }}">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div style="{{ $muted }}">{{ $pace['month_label'] }} · day {{ $pace['full_days'] }} of {{ $pace['days_in'] }}</div>
                <x-filament::badge :color="$pc">{{ $pl }}</x-filament::badge>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px;font-size:13px">
                <div><div style="{{ $muted }}">Budget{{ $pace['from_plan'] ? ' (plan)' : '' }}</div><strong>{{ $pace['budget'] ? R::money($pace['budget'], $cur) : '–' }}</strong></div>
                <div><div style="{{ $muted }}">Spent so far</div><strong>{{ R::money($pace['spent'], $cur) }}</strong>@if ($pace['pace'] !== null)<span style="{{ $muted }}"> · {{ round($pace['pace']) }}% of expected</span>@endif</div>
                <div><div style="{{ $muted }}">Month-end forecast</div><strong>{{ R::money($pace['forecast_spend'], $cur) }}</strong>@if ($pace['forecast_vs_budget'])<span style="{{ $muted }}"> · {{ round($pace['forecast_vs_budget']) }}% of budget</span>@endif</div>
                <div><div style="{{ $muted }}">Needed per day</div><strong>{{ $pace['daily_needed'] !== null ? R::money($pace['daily_needed'], $cur) : '–' }}</strong></div>
                <div><div style="{{ $muted }}">{{ R::label($pace['main_type']) ?: 'Results' }} so far → forecast</div><strong>{{ number_format($pace['results']) }} → {{ number_format($pace['forecast_results']) }}</strong>@if ($pace['target_results'])<span style="{{ $muted }}"> · target {{ number_format($pace['target_results']) }}</span>@endif</div>
                <div><div style="{{ $muted }}">Forecast cost / result</div><strong>{{ R::money($pace['forecast_cpr'], $cur) }}</strong></div>
            </div>
            <div style="{{ $muted }};margin-top:8px">Forecast = spent so far + last {{ config('planning.pacing.run_rate_days') }} days' daily average × {{ $pace['days_left'] }} days left.
                <a href="{{ \App\Filament\Pages\BudgetPlanner::getUrl(['client' => $client->id]) }}" style="color:#14B8A6">Budget plan</a></div>
        </div>
    </div>

    @if ($goals || count($t['by_type']) > 1)
        <x-filament::section>
            <x-slot name="heading">Results by goal</x-slot>
            <div style="display:flex;flex-wrap:wrap;gap:18px">
                @foreach ($t['by_type'] as $type => $n)
                    <div><div style="{{ $muted }}">{{ R::label($type) }} (goal)</div><div style="font-weight:600">{{ number_format($n) }}
                        @if (($t['type_spend'][$type] ?? 0) > 0 && $n > 0)<span style="{{ $muted }}"> · {{ R::money($t['type_spend'][$type] / $n, $cur) }} each</span>@endif</div></div>
                @endforeach
                @foreach ($goals as $label => $n)
                    <div><div style="{{ $muted }}">{{ $label }}</div><div style="font-weight:600">{{ is_numeric($n) ? number_format($n) : $n }}</div></div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @if ($alerts->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Open alerts</x-slot>
            @foreach ($alerts as $a)
                <div style="padding:6px 0;border-top:1px solid rgba(148,163,184,.2)">
                    <x-filament::badge :color="['high' => 'danger', 'medium' => 'warning', 'low' => 'gray'][$a->severity] ?? 'gray'" style="display:inline-flex">{{ ucfirst($a->severity) }}</x-filament::badge>
                    <strong style="margin-inline-start:6px">{{ $a->title }}</strong>
                    <div style="{{ $muted }};margin-top:2px">{{ $a->detail }}</div>
                </div>
            @endforeach
        </x-filament::section>
    @endif

    @if (count($funnel) > 1)
        <x-filament::section>
            <x-slot name="heading">Funnel</x-slot>
            @php $fTop = max(1, $funnel[0]['n']); @endphp
            <div style="display:grid;gap:8px">
                @foreach ($funnel as $f)
                    <div style="display:grid;grid-template-columns:150px 1fr 150px;gap:10px;align-items:center;font-size:13px">
                        <div>{{ $f['label'] }}</div>
                        <div style="height:14px;border-radius:6px;background:rgba(148,163,184,.18);overflow:hidden">
                            <div style="height:14px;width:{{ max(1, round(log10(max(1, $f['n'])) / log10(max(10, $fTop)) * 100)) }}%;background:#14B8A6"></div>
                        </div>
                        <div style="text-align:end"><strong>{{ number_format($f['n']) }}</strong>@if ($f['rate'] !== null)<span style="{{ $muted }}"> · {{ number_format($f['rate'], $f['rate'] < 1 ? 2 : 1) }}%</span>@endif</div>
                    </div>
                @endforeach
            </div>
            <div style="{{ $muted }};margin-top:6px">Bars use a log scale so small steps stay visible. % = share of the step before.</div>
        </x-filament::section>
    @endif

    @if (count($actions))
        <x-filament::section>
            <x-slot name="heading">Google conversions by action</x-slot>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">Conversion action</th><th style="padding:6px;text-align:start">Category</th>
                    <th style="padding:6px;text-align:end">Conversions</th><th style="padding:6px;text-align:end">Value</th></tr></thead>
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
            <div style="{{ $muted }};margin-top:6px">Count-only actions carry no value. Results above use primary conversion actions only.</div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">6-month trend</x-slot>
        <div style="display:grid;grid-template-columns:repeat({{ count($months) }},1fr);gap:10px;align-items:end;height:170px">
            @foreach ($months as $m => $v)
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end" title="{{ $m }}: {{ R::money($v['spend'], $cur) }} · {{ number_format($v['main_results']) }} results">
                    <div style="display:flex;gap:3px;align-items:flex-end;height:130px">
                        <div style="width:14px;border-radius:4px 4px 0 0;background:#14B8A6;height:{{ max(2, round($v['spend'] / $maxSpend * 130)) }}px"></div>
                        <div style="width:14px;border-radius:4px 4px 0 0;background:#6366f1;height:{{ max(2, round($v['main_results'] / $maxRes * 130)) }}px"></div>
                    </div>
                    <div style="{{ $muted }}">{{ \Carbon\Carbon::parse($m.'-01')->format('M') }}</div>
                </div>
            @endforeach
        </div>
        <div style="{{ $muted }};margin-top:6px"><span style="color:#14B8A6">■</span> Spend · <span style="color:#6366f1">■</span> Results (main goal each month)</div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            <span wire:click="openCampaign(null)" style="cursor:pointer">Campaigns</span>
            @if ($crumbs['campaign']) › <span wire:click="openAdSet(null)" style="cursor:pointer">{{ $crumbs['campaign'] }}</span> @endif
            @if ($crumbs['ad_set']) › {{ $crumbs['ad_set'] }} @endif
        </x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="text-align:start;{{ $muted }}">
                    <th style="padding:6px;text-align:start">{{ ['campaign' => 'Campaign', 'ad_set' => 'Ad set', 'ad' => 'Ad'][$level] }}</th>
                    <th style="padding:6px;text-align:start">Goal</th><th style="padding:6px;text-align:end">Spend</th><th style="padding:6px;text-align:end">Results</th>
                    <th style="padding:6px;text-align:end">Cost / result</th><th style="padding:6px;text-align:end">Impr.</th><th style="padding:6px;text-align:end">CTR</th>
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
                        <td style="padding:6px">{{ R::label($r['main_type']) }}</td>
                        <td style="padding:6px;text-align:end">{{ R::money($r['spend']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($r['main_results']) }}</td>
                        <td style="padding:6px;text-align:end">{{ R::money($r['cpr']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($r['impressions']) }}</td>
                        <td style="padding:6px;text-align:end">{{ $r['ctr'] ? number_format($r['ctr'], 2).'%' : '–' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:10px;{{ $muted }}">No data for this period yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if (count($top))
        <x-filament::section>
            <x-slot name="heading">Top ads</x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px">
                @foreach ($top as $ad)
                    <div style="{{ $card }};padding:8px">
                        @if ($ad['thumbnail'])
                            <img src="{{ $ad['thumbnail'] }}" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px">
                        @else
                            <div style="width:100%;aspect-ratio:1;border-radius:8px;background:rgba(148,163,184,.15)"></div>
                        @endif
                        <div style="font-size:12px;font-weight:600;margin-top:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $ad['name'] }}</div>
                        <div style="{{ $muted }}">{{ number_format($ad['main_results']) }} {{ R::label($ad['main_type']) }} · {{ R::money($ad['cpr'], $cur) }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
