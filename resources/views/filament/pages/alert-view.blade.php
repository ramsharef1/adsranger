@php
    $a = $this->record;
    $sev = \App\Support\Severity::normalize($a->severity);
    $critical = $sev === 'critical';
    $hist = $this->history();
@endphp
<x-filament-panels::page>
    <style>.fi-page-header-actions .fi-btn, [data-alert-page] .fi-btn { min-height: 44px; }</style>
    <div data-alert-page style="display:grid;gap:16px">
        @if ($critical)
            {{-- #175 spec A: offer phone alerts once, on a phone, from a Critical alert. Starter sees the lock line. --}}
            <div x-data="{ show: false, st: '', async init() { if (!window.adsrPush || !window.adsrPush.isPhone()) return; this.st = await window.adsrPush.state(); this.show = ['off', 'locked'].includes(this.st) }, async on() { this.st = await window.adsrPush.enable(); this.show = this.st !== 'on' } }"
                 x-show="show" x-cloak data-push-offer
                 style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 14px;border-radius:12px;border:1px solid rgba(15,118,110,.45);background:rgba(20,184,166,.08)">
                <x-filament::icon icon="heroicon-o-device-phone-mobile" style="width:20px;height:20px" />
                <span x-show="st !== 'locked'" style="flex:1">{{ __('Get an instant alert on your phone for Critical alerts') }}</span>
                <x-filament::button x-show="st !== 'locked'" x-on:click="on()" style="min-height:44px">{{ __('Turn on') }}</x-filament::button>
                <span x-show="st === 'locked'" style="flex:1">{{ __('Instant phone alerts are available on Pro.') }}</span>
            </div>
        @endif

        <x-filament::section>
            <dl style="display:grid;grid-template-columns:minmax(110px,max-content) 1fr;gap:10px 16px;margin:0">
                <dt style="opacity:.65">{{ __('Level') }}</dt>
                <dd style="margin:0"><x-filament::badge :color="\App\Support\Severity::color($sev)" :icon="\App\Support\Severity::icon($sev)">{{ \App\Support\Severity::label($sev) }}</x-filament::badge></dd>
                <dt style="opacity:.65">{{ __('Rule') }}</dt>
                <dd style="margin:0">{{ __(config("alerts.rules.{$a->rule_key}.label") ?? $a->rule_key) }}</dd>
                <dt style="opacity:.65">{{ __('Client') }}</dt>
                <dd style="margin:0">{{ $a->client?->name ?? '—' }}</dd>
                @if ($a->entity_name)
                    <dt style="opacity:.65">{{ __('Where') }}</dt>
                    <dd style="margin:0"><bdi>{{ $a->entity_name }}</bdi></dd>
                @endif
                <dt style="opacity:.65">{{ __('Since') }}</dt>
                <dd style="margin:0">{{ $a->created_at?->locale(app()->getLocale())->diffForHumans() }}</dd>
                <dt style="opacity:.65">{{ __('What we found') }}</dt>
                <dd style="margin:0"><bdi>{{ $a->localDetail() ?: $a->localTitle() }}</bdi></dd>
                <dt style="opacity:.65">{{ __('Status') }}</dt>
                <dd style="margin:0">{{ __($a->status) }}@if ($a->status === 'snoozed' && $a->snoozed_until) · {{ __('until :date', ['date' => $a->snoozed_until->translatedFormat('j M')]) }}@endif</dd>
                <dt style="opacity:.65">{{ __('Assigned to') }}</dt>
                <dd style="margin:0">{{ $a->assignee?->name ?? '—' }}</dd>
            </dl>
        </x-filament::section>

        <x-filament::section :heading="__('History')" collapsible>
            @forelse ($hist as $h)
                <p style="margin:0 0 6px;font-size:14px">{{ $h['what'] }} · {{ $h['who'] }} · <span style="opacity:.65">{{ $h['at']?->locale(app()->getLocale())->diffForHumans() }}</span></p>
            @empty
                <p style="margin:0;opacity:.65;font-size:14px">{{ __('No changes yet.') }}</p>
            @endforelse
        </x-filament::section>

        <div><a href="{{ \App\Filament\Resources\Alerts\AlertResource::getUrl('index') }}" style="display:inline-flex;align-items:center;min-height:44px">{{ __('All alerts') }}</a></div>
    </div>
</x-filament-panels::page>
