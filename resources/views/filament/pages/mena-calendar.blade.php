@php
    use App\Support\ResultResolver as R;
    use App\Support\MenaCalendar as M;
    $muted = 'opacity:.65;font-size:12px';
    $field = 'padding:5px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px';
    $cur = $client?->currency;
    $pct = fn ($x) => $x === null ? '–' : (($x >= 1 ? '+' : '−').number_format(abs($x - 1) * 100, 0).'%');
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <label style="{{ $muted }}" for="mc-market">{{ __('Market') }}</label>
        <select id="mc-market" wire:model.live="market" style="{{ $field }}">
            <option value="">{{ __('All markets') }}</option>
            @foreach ($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach
        </select>
        <label style="{{ $muted }}" for="mc-client">{{ __('Client history') }}</label>
        <select id="mc-client" wire:model.live="clientId" style="{{ $field }}">
            <option value="">{{ __('None') }}</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>
        <span style="{{ $muted }}">{{ __('Next 12 months · Hijri dates follow Umm al-Qura and can move ±1 day with moon sighting.') }}
            @if (! $intl)
                {{ __('Hijri dates are estimates: the calendar extension is missing on the server.') }}
            @endif
        </span>
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('Upcoming events') }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}">
                    <th style="padding:6px;text-align:start">{{ __('Event') }}</th><th style="padding:6px;text-align:start">{{ __('Dates') }}</th>
                    <th style="padding:6px;text-align:start">{{ __('Hijri') }}</th><th style="padding:6px;text-align:end">{{ __('In') }}</th>
                    @if ($client)<th style="padding:6px;text-align:end" title="{{ __('Last time vs the 28 days before') }}">{{ __(':client: spend · CPR', ['client' => $client->name]) }}</th>@endif
                    <th style="padding:6px;text-align:start">{{ __('Tip') }}</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($events as $e)
                    @php $s = $seasons[$e['key']] ?? null; $in = \Carbon\Carbon::parse($today)->diffInDays(\Carbon\Carbon::parse($e['start']), false); @endphp
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px"><strong>{{ $e['label'] }}</strong> <span dir="rtl" lang="ar" style="{{ $muted }}">{{ $e['ar'] }}</span>
                            @if ($e['markets'])<div style="{{ $muted }}">{{ implode(', ', $e['markets']) }}</div>@endif</td>
                        <td style="padding:6px;white-space:nowrap">{{ \Carbon\Carbon::parse($e['start'])->translatedFormat('D j M Y') }}@if ($e['days'] > 1) → {{ \Carbon\Carbon::parse($e['end'])->translatedFormat('j M') }}@endif
                            @if ($e['kind'] === 'hijri')<div style="{{ $muted }}">{{ $e['estimated'] ? __('estimated') : __('±1 day') }}</div>@endif</td>
                        <td style="padding:6px;white-space:nowrap">{{ $e['kind'] === 'hijri' ? M::hijriLabel($e['start']) : '' }}</td>
                        <td style="padding:6px;text-align:end;white-space:nowrap">{{ $in <= 0 ? __('now') : trans_choice(':count day|:count days', $in) }}</td>
                        @if ($client)
                            <td style="padding:6px;text-align:end">{{ $s ? $pct($s['spend']).' · '.$pct($s['cpr']) : '–' }}
                                @if ($s)<div style="{{ $muted }}">{{ \Carbon\Carbon::parse($s['start'])->translatedFormat('M Y') }}</div>@endif</td>
                        @endif
                        <td style="padding:6px;{{ $muted }}">{{ $e['tip'] }}</td>
                        <td style="padding:6px;text-align:end">
                            @if ($scenarioLocked)
                                <x-locked-button feature="scenario_planner" :label="__('Plan')" />
                            @else
                                <x-filament::button size="xs" color="gray" wire:click="plan('{{ $e['key'] }}', '{{ $e['start'] }}')">{{ __('Plan') }}</x-filament::button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:12px;{{ $muted }}">{{ __('No events in the next 12 months for this market.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if ($picked)
        <x-filament::section>
            <x-slot name="heading">{{ __('Scenario: :event (:from → :to, :days days)', ['event' => $picked['label'], 'from' => \Carbon\Carbon::parse($picked['start'])->translatedFormat('j M'), 'to' => \Carbon\Carbon::parse($picked['end'])->translatedFormat('j M Y'), 'days' => $picked['days']]) }}</x-slot>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px">
                <label style="{{ $muted }}" for="mc-client2">{{ __('Client') }}</label>
                <select id="mc-client2" wire:model.live="clientId" style="{{ $field }}">
                    <option value="">{{ __('Pick a client') }}</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
                <label style="{{ $muted }}" for="mc-change">{{ __('Daily budget change vs normal (%)') }}</label>
                <input id="mc-change" type="number" step="5" min="-90" max="300" wire:model.live.debounce.500ms="change" style="{{ $field }};width:90px">
            </div>
            @if (! $client)
                <div style="{{ $muted }}">{{ __('Pick a client to see the scenario.') }}</div>
            @elseif (! $scenario || $scenario['baseline_day_spend'] <= 0)
                <div style="{{ $muted }}">{{ __('This client has no spend in the last 28 days, so there\'s no baseline to plan from.') }}</div>
            @else
                @php $sc = $scenario; @endphp
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
                    <div style="{{ $card }}"><div style="{{ $muted }}">{{ __('Spend in the event') }}</div><div style="font-size:20px;font-weight:700">{{ R::money($sc['spend'], $cur) }}</div>
                        <div style="{{ $muted }}">{{ __('normal: :amount', ['amount' => R::money($sc['normal_spend'], $cur)]) }}</div></div>
                    <div style="{{ $card }}"><div style="{{ $muted }}">{{ __('Expected :result', ['result' => strtolower(R::label($sc['main_type'], app()->getLocale()) ?: __('results'))]) }}</div><div style="font-size:20px;font-weight:700">{{ $sc['results'] !== null ? number_format($sc['results']) : '–' }}</div>
                        <div style="{{ $muted }}">{{ __('at normal budget: :n', ['n' => $sc['normal_results'] !== null ? number_format($sc['normal_results']) : '–']) }}</div></div>
                    <div style="{{ $card }}"><div style="{{ $muted }}">{{ __('Expected cost per result') }}</div><div style="font-size:20px;font-weight:700">{{ R::money($sc['cpr'], $cur) }}</div>
                        <div style="{{ $muted }}">{{ __('last 28 days: :amount', ['amount' => R::money($sc['baseline_cpr'], $cur)]) }}</div></div>
                </div>
                <div style="{{ $muted }};margin-top:10px">
                    {{ __('Estimate only. Baseline = the last 28 days (:amount a day).', ['amount' => R::money($sc['baseline_day_spend'], $cur)]) }}
                    @if ($sc['source'] === 'history')
                        {{ __('Event effect on cost per result: :pct from this client\'s own :event in :month.', ['pct' => $pct($sc['cpr_index']), 'event' => $picked['label'], 'month' => \Carbon\Carbon::parse($sc['history']['start'])->translatedFormat('M Y')]) }}
                    @else
                        {{ __('Event effect on cost per result: :pct — a starting assumption until this client has data from a past :event.', ['pct' => $pct($sc['cpr_index']), 'event' => $picked['label']]) }}
                    @endif
                    {{ __('Extra budget raises cost per result with diminishing returns (×(1 + change)^:e).', ['e' => config('planning.elasticity')]) }}
                </div>
            @endif
        </x-filament::section>
    @endif
    @if ($scenarioLocked)
        <x-locked-modal feature="scenario_planner" :can-upgrade="$canUpgrade" />
    @endif
</x-filament-panels::page>
