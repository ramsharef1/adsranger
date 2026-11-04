@php
    use App\Support\ResultResolver as R;
    use App\Filament\Resources\Clients\ClientResource;
    $muted = 'opacity:.65;font-size:12px';
    $pill = fn ($on) => 'padding:5px 12px;border-radius:99px;font-size:13px;cursor:pointer;border:1px solid '.($on ? '#14B8A6' : 'rgba(148,163,184,.35)').';background:'.($on ? 'rgba(20,184,166,.12)' : 'transparent');
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
            <button type="button" wire:click="setDay('yesterday')" style="{{ $pill($day === 'yesterday') }}">Yesterday</button>
            <button type="button" wire:click="setDay('today')" style="{{ $pill($day === 'today') }}">Today (live)</button>
            <input type="date" wire:model.live="date" wire:change="setDay('custom')" max="{{ now($tz)->toDateString() }}"
                   style="padding:4px 8px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshToday">Refresh today</x-filament::button>
        </div>
        <div style="{{ $muted }}">{{ $from }} · {{ $tz }} · @if ($synced) data updated {{ $synced->diffForHumans() }} @else no sync yet @endif</div>
    </div>

    <x-filament::section>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}">
                    <th style="padding:6px;text-align:start">Client</th><th style="padding:6px;text-align:end">Spend</th>
                    <th style="padding:6px;text-align:start">Main result</th><th style="padding:6px;text-align:end">Cost / result</th>
                    <th style="padding:6px;text-align:end">Leads</th><th style="padding:6px;text-align:end">Messages</th>
                    <th style="padding:6px;text-align:end">Calls</th><th style="padding:6px;text-align:end">Purchases</th>
                    <th style="padding:6px;text-align:end">ROAS</th><th style="padding:6px;text-align:start">Alerts</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    @php $t = $r['t']; $c = $r['client']; @endphp
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px"><a href="{{ ClientResource::getUrl('view', ['record' => $c]) }}" style="color:#14B8A6;font-weight:600">{{ $c->name }}</a></td>
                        <td style="padding:6px;text-align:end">{{ R::money($t['spend'], $c->currency) }}</td>
                        <td style="padding:6px">{{ number_format($t['main_results']) }} <span style="{{ $muted }}">{{ R::label($t['main_type']) }}</span></td>
                        <td style="padding:6px;text-align:end">{{ R::money($t['cpr']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['leads']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['messages']) }}</td>
                        <td style="padding:6px;text-align:end" title="placed / confirmed / 20s+">{{ number_format($t['calls']) }}@if ($t['calls_confirmed'] || $t['calls_20s'])<span style="{{ $muted }}"> · {{ number_format($t['calls_confirmed']) }} · {{ number_format($t['calls_20s']) }}</span>@endif</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['purchases']) }}</td>
                        <td style="padding:6px;text-align:end">{{ $t['roas'] ? number_format($t['roas'], 2).'×' : '–' }}</td>
                        <td style="padding:6px">
                            @foreach ($r['alerts'] as $al)
                                <x-filament::badge :color="['high' => 'danger', 'medium' => 'warning', 'low' => 'gray'][$al->severity] ?? 'gray'" style="display:inline-flex">{{ $al->n }} {{ $al->severity }}</x-filament::badge>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="padding:12px;{{ $muted }}">No active clients yet. <a href="{{ ClientResource::getUrl('create') }}" style="color:#14B8A6">Add a client</a> and link its ad accounts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
