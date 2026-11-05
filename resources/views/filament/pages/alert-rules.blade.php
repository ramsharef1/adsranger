@php
    $muted = 'opacity:.65;font-size:12px';
    $inp = 'width:90px;padding:4px 8px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $names = ['min_days' => 'days', 'min_spend' => 'min. spend', 'days' => 'days', 'pct' => '%', 'min_results' => 'min. results', 'failures' => 'failures', 'from_day' => 'from day of month', 'min_rejected' => 'min. rejected ads', 'min_clicks' => 'min. clicks'];
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">{{ __('Defaults follow the AdsRanger spec. Spend thresholds are in each client\'s currency. In-app alerts go to everyone; email is opt-in per person on "My notifications".') }}</x-slot>
        @foreach ($defs as $k => $d)
            @php $isLocked = in_array($k, $locked, true); @endphp
            <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;padding:10px 0;border-top:1px solid rgba(148,163,184,.2)">
                <div style="min-width:240px;flex:1">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:600">
                        <input type="checkbox" wire:model="rules.{{ $k }}.enabled" @disabled(! $edit || $isLocked)> {{ __($d['label']) }}
                        <x-filament::badge :color="['high' => 'danger', 'medium' => 'warning', 'low' => 'gray'][$d['severity']]" style="display:inline-flex">{{ __(ucfirst($d['severity'])) }}</x-filament::badge>
                        @if ($isLocked)
                            <x-locked-button feature="alert_rules_pro" />
                        @endif
                    </label>
                    <div style="{{ $muted }};margin-top:2px">{{ __($d['help']) }}</div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    @foreach ($d['thresholds'] as $tk => $dv)
                        <label style="{{ $muted }};display:flex;gap:6px;align-items:center">{{ __($names[$tk] ?? $tk) }}
                            <input type="number" min="0" step="any" wire:model="rules.{{ $k }}.t.{{ $tk }}" style="{{ $inp }}" @disabled(! $edit || $isLocked)>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
        @if ($edit)
            <div style="display:flex;gap:8px;margin-top:12px">
                <x-filament::button wire:click="save">{{ __('Save rules') }}</x-filament::button>
                <x-filament::button color="gray" wire:click="runNow" icon="heroicon-o-play">{{ __('Run now for yesterday') }}</x-filament::button>
            </div>
        @endif
    </x-filament::section>
    @if ($locked)
        <x-locked-modal feature="alert_rules_pro" :can-upgrade="$canUpgrade" />
    @endif
</x-filament-panels::page>
