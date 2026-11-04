@php
    use App\Support\ResultResolver as R;
    use App\Support\Pacing;
    use App\Filament\Resources\Clients\ClientResource;
    $muted = 'opacity:.65;font-size:12px';
    $pill = fn ($on) => 'padding:5px 12px;border-radius:99px;font-size:13px;cursor:pointer;border:1px solid '.($on ? '#14B8A6' : 'rgba(148,163,184,.35)').';background:'.($on ? 'rgba(20,184,166,.12)' : 'transparent');
    $first = $rows[0]['p'] ?? null;
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
            <span style="{{ $muted }}">Sort:</span>
            <button type="button" wire:click="setSort('health')" style="{{ $pill($sort === 'health') }}">Lowest health first</button>
            <button type="button" wire:click="setSort('pace')" style="{{ $pill($sort === 'pace') }}">Furthest off pace</button>
            <button type="button" wire:click="setSort('name')" style="{{ $pill($sort === 'name') }}">Name</button>
            @if ($canManage)
                <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="recalc">Recalculate scores</x-filament::button>
            @endif
        </div>
        <div style="{{ $muted }}">
            @if ($first) {{ $first['month_label'] }} · day {{ $first['full_days'] }} of {{ $first['days_in'] }} complete · @endif
            {{ $tz }} · @if ($scored) scores updated {{ \Illuminate\Support\Carbon::parse($scored)->diffForHumans() }} @else scores run nightly @endif
        </div>
    </div>

    <x-filament::section>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}">
                    <th style="padding:6px;text-align:start">Client</th>
                    <th style="padding:6px;text-align:start">Health</th>
                    <th style="padding:6px;text-align:end">Budget</th>
                    <th style="padding:6px;text-align:end">Spent</th>
                    <th style="padding:6px;text-align:end" title="Spent vs the budget expected by now">Pace</th>
                    <th style="padding:6px;text-align:end">Month-end forecast</th>
                    <th style="padding:6px;text-align:end">Results · forecast · target</th>
                    <th style="padding:6px;text-align:end" title="Daily spend needed to land exactly on budget">Needed / day</th>
                    <th style="padding:6px;text-align:start">Status</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    @php $c = $r['client']; $p = $r['p']; [$sl, $sc] = Pacing::label($p['status']); $cur = $c->currency; @endphp
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px"><a href="{{ ClientResource::getUrl('view', ['record' => $c]) }}" style="color:#14B8A6;font-weight:600">{{ $c->name }}</a></td>
                        <td style="padding:6px" title="{{ collect((array) $c->health_parts)->map(fn ($x, $k) => ucfirst($k).': '.$x['points'].'/'.$x['max'].' · '.$x['note'])->implode("\n") }}">
                            <x-filament::badge :color="$r['band'][1]" style="display:inline-flex">{{ $c->health_score ?? '–' }} · {{ $r['band'][0] }}</x-filament::badge>
                        </td>
                        <td style="padding:6px;text-align:end">{{ $p['budget'] ? R::money($p['budget'], $cur) : '–' }}@if ($p['from_plan'])<span style="{{ $muted }}" title="From the budget plan"> · plan</span>@endif</td>
                        <td style="padding:6px;text-align:end">{{ R::money($p['spent'], $cur) }}</td>
                        <td style="padding:6px;text-align:end">{{ $p['pace'] !== null ? round($p['pace']).'%' : '–' }}</td>
                        @if ($forecastLocked)
                            <td style="padding:6px;text-align:end"><x-locked-button feature="forecast" /></td>
                            <td style="padding:6px;text-align:end">{{ number_format($p['results']) }} · <x-locked-button feature="forecast" /> · {{ $p['target_results'] ? number_format($p['target_results']) : '–' }}
                        @else
                            <td style="padding:6px;text-align:end">{{ R::money($p['forecast_spend'], $cur) }}@if ($p['forecast_vs_budget'])<span style="{{ $muted }}"> · {{ round($p['forecast_vs_budget']) }}%</span>@endif</td>
                            <td style="padding:6px;text-align:end">{{ number_format($p['results']) }} · {{ number_format($p['forecast_results']) }} · {{ $p['target_results'] ? number_format($p['target_results']) : '–' }}
                        @endif
                            <div style="{{ $muted }}">{{ R::label($p['main_type']) }}</div></td>
                        <td style="padding:6px;text-align:end">{{ $p['daily_needed'] !== null ? R::money($p['daily_needed'], $cur) : '–' }}</td>
                        <td style="padding:6px"><x-filament::badge :color="$sc" style="display:inline-flex">{{ $sl }}</x-filament::badge></td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="padding:12px;{{ $muted }}">No active clients yet. <a href="{{ ClientResource::getUrl('create') }}" style="color:#14B8A6">Add a client</a> with a monthly budget to see pacing.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="{{ $muted }};margin-top:8px">
            Pace = spend so far vs the budget expected by the end of yesterday (±{{ config('planning.pacing.tolerance') }}% counts as on pace).
            Forecast = spend so far + the last {{ config('planning.pacing.run_rate_days') }} days' daily average × days left. Budgets come from the budget plan, else the client's monthly budget.
            Health = results vs target 40 · pacing 20 · 2-week trend 20 · tracking and accounts 20 (hover the score for details).
        </div>
    </x-filament::section>
    @if ($forecastLocked)
        <x-locked-modal feature="forecast" :can-upgrade="$canUpgrade" />
    @endif
</x-filament-panels::page>
