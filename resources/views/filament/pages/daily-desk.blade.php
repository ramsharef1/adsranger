@php
    use App\Support\ResultResolver as R;
    use App\Filament\Resources\Clients\ClientResource;
    $muted = 'opacity:.65;font-size:12px';
    $pill = fn ($on) => 'padding:5px 12px;border-radius:99px;font-size:13px;cursor:pointer;border:1px solid '.($on ? '#14B8A6' : 'rgba(148,163,184,.35)').';background:'.($on ? 'rgba(20,184,166,.12)' : 'transparent');
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
            <button type="button" wire:click="setDay('yesterday')" style="{{ $pill($day === 'yesterday') }}">{{ __('Yesterday') }}</button>
            <button type="button" wire:click="setDay('today')" style="{{ $pill($day === 'today') }}">{{ __('Today (live)') }}</button>
            <input type="date" wire:model.live="date" wire:change="setDay('custom')" max="{{ now($tz)->toDateString() }}"
                   style="padding:4px 8px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshToday">{{ __('Refresh today') }}</x-filament::button>
        </div>
        <div style="{{ $muted }}">{{ $from }} · {{ $tz }} · @if ($synced) {{ __('data updated :when', ['when' => $synced->diffForHumans()]) }} @else {{ __('no sync yet') }} @endif</div>
    </div>

    <x-filament::section>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}">
                    <th style="padding:6px;text-align:start">{{ __('Client') }}</th><th style="padding:6px;text-align:end">{{ __('Spend') }}</th>
                    <th style="padding:6px;text-align:start">{{ __('Main result') }}</th><th style="padding:6px;text-align:end">{{ __('Cost / result') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('Leads') }}</th><th style="padding:6px;text-align:end">{{ __('Messages') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('Calls') }}</th><th style="padding:6px;text-align:end">{{ __('Purchases') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('ROAS') }}</th><th style="padding:6px;text-align:start">{{ __('Alerts') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    @php $t = $r['t']; $c = $r['client']; @endphp
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px"><a href="{{ ClientResource::getUrl('view', ['record' => $c]) }}" style="color:#14B8A6;font-weight:600">{{ $c->name }}</a></td>
                        <td style="padding:6px;text-align:end">{{ R::money($t['spend'], $c->currency) }}</td>
                        <td style="padding:6px">{{ number_format($t['main_results']) }} <span style="{{ $muted }}">{{ R::label($t['main_type'], app()->getLocale()) }}</span></td>
                        <td style="padding:6px;text-align:end">{{ R::money($t['cpr']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['leads']) }}</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['messages']) }}</td>
                        <td style="padding:6px;text-align:end" title="{{ __('placed / confirmed / 20s+') }}">{{ number_format($t['calls']) }}@if ($t['calls_confirmed'] || $t['calls_20s'])<span style="{{ $muted }}"> · {{ number_format($t['calls_confirmed']) }} · {{ number_format($t['calls_20s']) }}</span>@endif</td>
                        <td style="padding:6px;text-align:end">{{ number_format($t['purchases']) }}</td>
                        <td style="padding:6px;text-align:end">{{ $t['roas'] ? number_format($t['roas'], 2).'×' : '–' }}</td>
                        <td style="padding:6px">
                            @foreach ($r['alerts'] as $al)
                                <x-filament::badge :color="\App\Support\Severity::color($al->severity)" :icon="\App\Support\Severity::icon($al->severity)" style="display:inline-flex">{{ $al->n }} {{ \App\Support\Severity::label($al->severity) }}</x-filament::badge>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="padding:12px;{{ $muted }}">{{ __('No active clients yet.') }} <a href="{{ ClientResource::getUrl('create') }}" style="color:#14B8A6">{{ __('Add a client') }}</a> {{ __('and link its ad accounts.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
