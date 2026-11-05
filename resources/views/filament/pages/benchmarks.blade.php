@php
    use App\Support\ResultResolver as R;
    use App\Support\Benchmarks as B;
    $muted = 'opacity:.65;font-size:12px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px';
    $th = 'padding:6px;text-align:end';
    $fmt = fn ($m, $v) => $v === null ? '–' : match ($m) { 'ctr' => number_format($v, 2).'%', 'roas' => number_format($v, 2).'×', default => R::money($v) };
    $vs = function ($v, $med, $m) {
        $d = B::versus($v, $med, $m);
        if (! $d) return '';
        $color = $d['good'] === null ? 'inherit' : ($d['good'] ? '#16a34a' : '#dc2626');
        return '<span style="color:'.$color.';font-size:12px"> '.($d['pct'] >= 0 ? '▲' : '▼').number_format(abs($d['pct']), 0).'%</span>';
    };
    $cellName = fn ($c) => ($verticals[$c['vertical']] ?? ucfirst($c['vertical'])).' · '.($countries[$c['market']] ?? $c['market']).' · '.R::label($c['type'], app()->getLocale());
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Your clients vs your agency\'s medians') }}</x-slot>
        <div style="{{ $muted }};margin-bottom:8px">{{ __('Last :days days (:from → :to), money in USD. A client is compared with the clients in the same vertical, market and main result (shown when the group has :min+ clients). ▲▼ = this client vs the group median; green is better.', ['days' => config('insights.benchmarks.days'), 'from' => $internal['from'], 'to' => $internal['to'], 'min' => config('insights.benchmarks.min_clients')]) }}</div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Client') }}</th><th style="padding:6px;text-align:start">{{ __('Group') }}</th>
                    @foreach (B::METRICS as $m => $label)<th style="{{ $th }}">{{ __($label) }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($internal['rows'] as $r)
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px;font-weight:600">{{ $r['name'] }}</td>
                        <td style="padding:6px">{{ $cellName($r) }}@if (! $r['cell'])<div style="{{ $muted }}">{{ __('group too small to compare') }}</div>@endif</td>
                        @foreach (array_keys(B::METRICS) as $m)
                            <td style="{{ $th }}">{{ $fmt($m, $r[$m]) }}{!! $r['cell'] ? $vs($r[$m], $r['cell'][$m], $m) : '' !!}
                                @if ($r['cell'])<div style="{{ $muted }}">{{ __('median :value', ['value' => $fmt($m, $r['cell'][$m])]) }}</div>@endif</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:12px;{{ $muted }}">{{ __('No client spend in the last :days days.', ['days' => config('insights.benchmarks.days')]) }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('MENA benchmarks network') }}</x-slot>
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
            <div style="max-width:720px;font-size:13px">
                {{ __('Compare with other agencies across the region. Only agencies that join see the network, and only medians are shared. No client names, account IDs, budgets or agency names ever leave your workspace.') }}
                <strong>{{ trans_choice('{1} Joining is opt-in. Medians show once at least :count agency is in a group.|[2,*] Joining is opt-in. Medians show once at least :count agencies are in a group.', (int) config('insights.benchmarks.min_agencies')) }}</strong>
                <div style="{{ $muted }};margin-top:4px">{{ __('Status: :status.', ['status' => $agency->benchmarks_opt_in ? __('joined on :date', ['date' => $agency->benchmarks_opt_in_at?->toDateString()]) : __('not joined')]) }}</div>
            </div>
            @if ($canToggle)
                <x-filament::button :color="$agency->benchmarks_opt_in ? 'gray' : 'primary'" wire:click="toggleNetwork">{{ $agency->benchmarks_opt_in ? __('Leave the network') : __('Join the network') }}</x-filament::button>
            @else
                <span style="{{ $muted }}">{{ __('Only owners and admins can change this.') }}</span>
            @endif
        </div>
        @if ($network)
            <div style="{{ $muted }};margin:10px 0 6px">{{ __(':from → :to, USD, refreshed every :hours hours.', ['from' => $network['from'], 'to' => $network['to'], 'hours' => config('insights.benchmarks.cache_hours')]) }}</div>
            @if (! $network['cells'])
                <div style="{{ $card }}">{{ __('No group has :min+ agencies yet. Benchmarks appear here as more agencies join.', ['min' => config('insights.benchmarks.min_agencies')]) }}</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Group') }}</th><th style="{{ $th }}">{{ __('Agencies') }}</th>
                            @foreach (B::METRICS as $m => $label)<th style="{{ $th }}">{{ __('Median :metric', ['metric' => __($label)]) }}</th>@endforeach</tr></thead>
                        <tbody>
                        @foreach ($network['cells'] as $c)
                            <tr style="border-top:1px solid rgba(148,163,184,.2)"><td style="padding:6px">{{ $cellName($c) }}</td><td style="{{ $th }}">{{ $c['agencies_band'] }}</td>
                                @foreach (array_keys(B::METRICS) as $m)<td style="{{ $th }}">{{ $fmt($m, $c[$m]) }}</td>@endforeach</tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
