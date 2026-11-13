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
                        <x-filament::badge :color="\App\Support\Severity::color($d['severity'])" :icon="\App\Support\Severity::icon($d['severity'])" style="display:inline-flex">{{ \App\Support\Severity::label($d['severity']) }}</x-filament::badge>
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
    {{-- FS-21 (R4): alert on-call rota, Friday and Saturday included. --}}
    <x-filament::section data-on-call>
        <x-slot name="heading">{{ __('On-call rota') }}</x-slot>
        <x-slot name="description">{{ __('New Critical alerts are assigned to the person on call that day, and their phone hears Critical alerts even in quiet hours. Leave a day empty to keep the usual behaviour.') }}</x-slot>
        @if ($today)<p style="margin:0 0 8px;font-size:13.5px">{{ __('On call today: :name', ['name' => $today->name]) }}</p>@endif
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
            @foreach ([6 => __('Saturday'), 7 => __('Sunday'), 1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday')] as $d => $dn)
                <label style="display:grid;gap:4px;font-size:13px">{{ $dn }}
                    <select wire:model="onCall.{{ $d }}" @disabled(! $editRota) style="min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        <option value="">{{ __('Nobody') }}</option>
                        @foreach ($members as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select></label>
            @endforeach
        </div>
        @if ($editRota)<div style="margin-top:10px"><x-filament::button wire:click="saveOnCall">{{ __('Save rota') }}</x-filament::button></div>@endif
    </x-filament::section>
    @if ($locked)
        <x-locked-modal feature="alert_rules_pro" :can-upgrade="$canUpgrade" />
    @endif
</x-filament-panels::page>
