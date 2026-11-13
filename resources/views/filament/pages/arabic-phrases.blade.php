@php
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $copyBtn = 'min-height:44px;padding:0 12px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent;font-size:13px;flex:none';
    $typeLabels = ['dialect' => __('Dialect words'), 'spelling' => __('Clean spelling'), 'cta' => __('Call to action swapped')];
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">
        <div><label for="ap-client" style="{{ $label }}">{{ __('Client') }}</label>
            <select id="ap-client" wire:model.live="clientId" style="{{ $field }}"><option value="">{{ __('No client') }}</option>@foreach ($clients as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div><label for="ap-d" style="{{ $label }}">{{ __('Dialect') }}</label>
            <select id="ap-d" wire:model.live="dialect" style="{{ $field }}">@foreach ($dialects as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div><label for="ap-s" style="{{ $label }}">{{ __('Sector') }}</label>
            <select id="ap-s" wire:model.live="sector" style="{{ $field }}"><option value="">{{ __('All sectors') }}</option>@foreach ($sectors as $k => $v)<option value="{{ $k }}">{{ __($v) }}</option>@endforeach</select></div>
        <div><label for="ap-se" style="{{ $label }}">{{ __('Season') }}</label>
            <select id="ap-se" wire:model.live="season" style="{{ $field }}">@foreach ($seasons as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div><label for="ap-k" style="{{ $label }}">{{ __('Type') }}</label>
            <select id="ap-k" wire:model.live="kind" style="{{ $field }}"><option value="">{{ __('All') }}</option><option value="cta">{{ __('Call to action') }}</option><option value="offer">{{ __('Offer line') }}</option></select></div>
    </div>

    <x-filament::section data-phrases>
        <x-slot name="heading">{{ __('Phrases') }}</x-slot>
        <x-slot name="description">{{ $draft ? __('Draft set: written by our team and still being reviewed. Check each line before you use it.') : '' }}</x-slot>
        @if (! $phrases)
            <p style="margin:0;opacity:.75">{{ __('No phrases for this choice yet. Try another season or sector.') }}</p>
        @else
            <ul style="margin:0;padding:0;list-style:none;display:grid;gap:8px">
                @foreach ($phrases as $p)
                    <li x-data="{ ok: false }" style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;border-top:1px solid rgba(148,163,184,.2);padding-top:8px">
                        <span lang="ar" dir="rtl" style="font-size:15px">{{ $p['text'] }}</span>
                        <span style="display:flex;gap:8px;align-items:center"><span style="font-size:12px;opacity:.7">{{ $p['kind'] === 'cta' ? __('Call to action') : __('Offer line') }} · {{ $seasons[$p['season']] ?? $p['season'] }}</span>
                            <button type="button" style="{{ $copyBtn }}" x-on:click="navigator.clipboard.writeText(@js($p['text'])); ok = true; setTimeout(() => ok = false, 1500)"><span x-show="! ok">{{ __('Copy') }}</span><span x-show="ok" x-cloak>{{ __('Copied') }}</span></button></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament::section data-variants>
        <x-slot name="heading">{{ __('Variants of an ad line') }}</x-slot>
        <x-slot name="description">{{ __('Rule-based, no AI: swaps dialect words, gives a clean spelling, and swaps a known call to action for this dialect\'s ones.') }}</x-slot>
        <label for="ap-line" style="{{ $label }}">{{ __('Your ad line') }}</label>
        <textarea id="ap-line" wire:model.live.debounce.500ms="line" rows="2" maxlength="300" dir="auto" lang="ar" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:15px"></textarea>
        @if (trim($line) !== '')
            @if (! $variants)
                <p style="margin:8px 0 0;opacity:.75">{{ __('No changes for this dialect. The line has no words or calls to action we know how to swap.') }}</p>
            @else
                <ul style="margin:10px 0 0;padding:0;list-style:none;display:grid;gap:8px">
                    @foreach ($variants as $v)
                        <li x-data="{ ok: false }" style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap">
                            <span><span style="font-size:12px;opacity:.7">{{ $typeLabels[$v['type']] }}:</span> <span lang="ar" dir="rtl" style="font-size:15px">{{ $v['text'] }}</span></span>
                            <button type="button" style="{{ $copyBtn }}" x-on:click="navigator.clipboard.writeText(@js($v['text'])); ok = true; setTimeout(() => ok = false, 1500)"><span x-show="! ok">{{ __('Copy') }}</span><span x-show="ok" x-cloak>{{ __('Copied') }}</span></button>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
