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
    $cellName = fn ($c) => ($verticals[$c['vertical']] ?? ucfirst($c['vertical'])).' · '.($countries[$c['market']] ?? $c['market']).' · '.R::label($c['type']);
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Your clients vs your agency's medians</x-slot>
        <div style="{{ $muted }};margin-bottom:8px">Last {{ config('insights.benchmarks.days') }} days ({{ $internal['from'] }} → {{ $internal['to'] }}), money in USD. A client is compared with the clients in the same vertical, market and main result
            (shown when the group has {{ config('insights.benchmarks.min_clients') }}+ clients). ▲▼ = this client vs the group median; green is better.</div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">Client</th><th style="padding:6px;text-align:start">Group</th>
                    @foreach (B::METRICS as $m => $label)<th style="{{ $th }}">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($internal['rows'] as $r)
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px;font-weight:600">{{ $r['name'] }}</td>
                        <td style="padding:6px">{{ $cellName($r) }}@if (! $r['cell'])<div style="{{ $muted }}">group too small to compare</div>@endif</td>
                        @foreach (array_keys(B::METRICS) as $m)
                            <td style="{{ $th }}">{{ $fmt($m, $r[$m]) }}{!! $r['cell'] ? $vs($r[$m], $r['cell'][$m], $m) : '' !!}
                                @if ($r['cell'])<div style="{{ $muted }}">median {{ $fmt($m, $r['cell'][$m]) }}</div>@endif</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:12px;{{ $muted }}">No client spend in the last {{ config('insights.benchmarks.days') }} days.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">MENA benchmarks network</x-slot>
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
            <div style="max-width:720px;font-size:13px">
                Compare with other agencies across the region. It is <strong>opt-in</strong>: only agencies that join see the network, and only medians are shared.
                No client names, account IDs, budgets or agency names ever leave your workspace, and a group is shown only when at least
                <strong>{{ config('insights.benchmarks.min_agencies') }} agencies</strong> are in it.
                <div style="{{ $muted }};margin-top:4px">Status: {{ $agency->benchmarks_opt_in ? 'joined on '.$agency->benchmarks_opt_in_at?->toDateString() : 'not joined' }}.</div>
            </div>
            @if ($canToggle)
                <x-filament::button :color="$agency->benchmarks_opt_in ? 'gray' : 'primary'" wire:click="toggleNetwork">{{ $agency->benchmarks_opt_in ? 'Leave the network' : 'Join the network' }}</x-filament::button>
            @else
                <span style="{{ $muted }}">Only owners and admins can change this.</span>
            @endif
        </div>
        @if ($network)
            <div style="{{ $muted }};margin:10px 0 6px">{{ $network['from'] }} → {{ $network['to'] }}, USD, refreshed every {{ config('insights.benchmarks.cache_hours') }} hours.</div>
            @if (! $network['cells'])
                <div style="{{ $card }}">No group has {{ config('insights.benchmarks.min_agencies') }}+ agencies yet. Benchmarks appear here as more agencies join.</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">Group</th><th style="{{ $th }}">Agencies</th>
                            @foreach (B::METRICS as $m => $label)<th style="{{ $th }}">Median {{ $label }}</th>@endforeach</tr></thead>
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
