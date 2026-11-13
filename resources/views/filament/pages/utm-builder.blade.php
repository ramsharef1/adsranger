@php
    $muted = 'opacity:.65;font-size:12px';
    $field = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $fields = [
        ['source', 'Source', 'utm_source', 'facebook, google, newsletter'],
        ['medium', 'Traffic medium', 'utm_medium', 'cpc, paid_social, email'],
        ['campaign', 'Campaign', 'utm_campaign', 'ramadan_2027_offer'],
        ['term', 'Keyword', 'utm_term', '{keyword}'],
        ['utmId', 'Campaign ID', 'utm_id', '{campaignid}'],
    ];
@endphp
<x-filament-panels::page>
    <style>.ar-err{color:var(--danger-600)}.dark .ar-err{color:var(--danger-400)}.ar-warn{color:var(--warning-700)}.dark .ar-warn{color:var(--warning-400)}.ar-tap{min-height:44px;min-width:44px}</style>
    <div style="{{ $muted }}">{{ __('Add tracking to a landing page address so GA4 and your reports show which network, campaign and ad brought each visit.') }}</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:16px;align-items:start">
        <x-filament::section>
            <x-slot name="heading">{{ __('Build a link') }}</x-slot>
            <div style="display:grid;gap:12px">
                <div>
                    <label for="utm-base" style="{{ $label }}">{{ __('Landing page address') }}</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <input id="utm-base" type="url" wire:model.live.debounce.400ms="baseUrl" placeholder="https://example.com/offer" dir="ltr" style="{{ $field }};flex:1;min-width:200px">
                        <x-filament::button class="ar-tap" color="gray" icon="heroicon-o-signal" wire:click="checkLink" wire:loading.attr="disabled" wire:target="checkLink">{{ __('Check page') }}</x-filament::button>
                    </div>
                    @error('baseUrl')<div class="ar-err" style="font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
                    @if ($invalid)<div class="ar-err" style="font-size:12px;margin-top:4px">{{ __('Enter a full http(s) address.') }}</div>@endif
                </div>

                <div>
                    <label for="utm-preset" style="{{ $label }}">{{ __('Network preset') }}</label>
                    <select id="utm-preset" wire:model.live="preset" style="{{ $field }}">
                        <option value="">{{ __('Fill in by hand') }}</option>
                        @foreach ($presets as $k => $p)<option value="{{ $k }}">{{ __($p['label']) }}</option>@endforeach
                    </select>
                    <div style="{{ $muted }};margin-top:4px">{{ __('Presets use each network\'s own placeholders (like {campaignid}), which the network fills in when someone clicks.') }}</div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
                    @foreach ($fields as [$prop, $name, $param, $ph])
                        <div>
                            <label for="utm-{{ $prop }}" style="{{ $label }}">{{ __($name) }} <span style="{{ $muted }}" dir="ltr">{{ $param }}</span></label>
                            <input id="utm-{{ $prop }}" type="text" wire:model.live.debounce.400ms="{{ $prop }}" placeholder="{{ $ph }}" dir="ltr" style="{{ $field }}">
                        </div>
                    @endforeach
                </div>

                <div>
                    <label for="utm-content" style="{{ $label }}">{{ __('Ad content') }} <span style="{{ $muted }}" dir="ltr">utm_content</span></label>
                    <textarea id="utm-content" wire:model.live.debounce.400ms="content" rows="3" placeholder="video_30s&#10;carousel_a" dir="ltr" style="{{ $field }};resize:vertical"></textarea>
                    <div style="{{ $muted }};margin-top:4px">{{ __('One value per line makes one link per ad (up to 50).') }}</div>
                </div>
            </div>
        </x-filament::section>

        <div style="display:grid;gap:16px;min-width:0">
            <x-filament::section>
                <x-slot name="heading">{{ __('Your links') }}</x-slot>
                @forelse ($links as $i => $link)
                    <div x-data="{ copied: false }" style="display:flex;gap:8px;align-items:flex-start;padding:8px 0;{{ $i ? 'border-top:1px solid rgba(148,163,184,.2)' : '' }}">
                        <code id="utm-link-{{ $i }}" dir="ltr" style="flex:1;min-width:0;font-size:12px;word-break:break-all;text-align:start">{{ $link }}</code>
                        <x-filament::button class="ar-tap" size="sm" color="gray" icon="heroicon-o-clipboard" :aria-label="__('Copy link :n', ['n' => $i + 1])"
                            x-on:click="navigator.clipboard.writeText(@js($link)).then(() => { copied = true; setTimeout(() => copied = false, 1500) }).catch(() => { const r = document.createRange(); r.selectNodeContents(document.getElementById('utm-link-{{ $i }}')); const s = window.getSelection(); s.removeAllRanges(); s.addRange(r) })">
                            <span x-show="! copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                        </x-filament::button>
                    </div>
                @empty
                    <div style="{{ $muted }}">{{ __('Enter a landing page address to see the tracked link.') }}</div>
                @endforelse

                @if (count($warnings))
                    <ul class="ar-warn" style="margin:10px 0 0;padding-inline-start:18px;display:grid;gap:4px;font-size:12px">
                        @foreach ($warnings as $w)<li>{{ __($w) }}</li>@endforeach
                    </ul>
                @endif
            </x-filament::section>

            @if ($check)
                <x-filament::section>
                    <x-slot name="heading">{{ __('Page check') }}</x-slot>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                        <x-filament::badge :color="$check['color']">{{ __($check['label']) }}</x-filament::badge>
                        @if ($check['status_code'])<span style="{{ $muted }}">HTTP {{ $check['status_code'] }}</span>@endif
                        @isset($check['ms'])<span style="{{ $muted }}">{{ __(':ms ms', ['ms' => number_format($check['ms'])]) }}</span>@endisset
                        @if (! empty($check['redirects']))<span style="{{ $muted }}">{{ trans_choice('{1} :count redirect|[2,*] :count redirects', $check['redirects'], ['count' => $check['redirects']]) }}</span>@endif
                    </div>
                    @if (! empty($check['final_url']) && $check['final_url'] !== $check['url'])
                        <div style="{{ $muted }};margin-top:6px">{{ __('ends at :url', ['url' => '']) }}<span dir="ltr" style="word-break:break-all">{{ $check['final_url'] }}</span></div>
                    @endif
                    @if ($check['error'])<div style="{{ $muted }};margin-top:6px">{{ $check['error'] }}</div>@endif
                    @if (in_array($check['state'], ['redirects', 'broken'], true))
                        <div class="ar-warn" style="font-size:12px;margin-top:6px">{{ __('Redirects can drop UTM parameters. Use the final address as the landing page if you can.') }}</div>
                    @endif
                </x-filament::section>
            @endif
        </div>
    </div>
    {{-- XS-21 (R5): campaign name builder with the Hijri month token (RMD-1448). --}}
    @php $nf = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%'; $nl = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px'; @endphp
    <x-filament::section data-campaign-name>
        <x-slot name="heading">{{ __('Campaign name') }}</x-slot>
        <x-slot name="description">{{ __('Build a consistent name. The Hijri month token (for example RMD-1448 for Ramadan 1448) lets you find every Ramadan campaign across years.') }}</x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end">
            <div><label for="cn-client" style="{{ $nl }}">{{ __('Client code') }}</label><input id="cn-client" wire:model.live.debounce.300ms="name.client" maxlength="20" placeholder="ZAD" style="{{ $nf }}"></div>
            <div><label for="cn-obj" style="{{ $nl }}">{{ __('Objective') }}</label><select id="cn-obj" wire:model.live="name.objective" style="{{ $nf }}">@foreach (\App\Support\CampaignName::OBJECTIVES as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach</select></div>
            <div><label for="cn-mk" style="{{ $nl }}">{{ __('Market') }}</label><input id="cn-mk" wire:model.live.debounce.300ms="name.market" maxlength="8" placeholder="KSA" style="{{ $nf }}"></div>
            <div><label for="cn-x" style="{{ $nl }}">{{ __('Extra (offer, audience)') }}</label><input id="cn-x" wire:model.live.debounce.300ms="name.extra" maxlength="30" placeholder="IFTAR-OFFER" style="{{ $nf }}"></div>
            <div><label for="cn-d" style="{{ $nl }}">{{ __('Launch date') }}</label><input id="cn-d" type="date" wire:model.live="name.date" style="{{ $nf }}"></div>
            <div><label for="cn-sep" style="{{ $nl }}">{{ __('Separator') }}</label><select id="cn-sep" wire:model.live="name.sep" style="{{ $nf }}"><option value="_">_</option><option value="|">|</option><option value="-">-</option></select></div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px">
            <label style="display:flex;gap:6px;align-items:center;min-height:44px;font-size:13px"><input type="checkbox" wire:model.live="name.hijri">{{ __('Hijri month') }}</label>
            <label style="display:flex;gap:6px;align-items:center;min-height:44px;font-size:13px"><input type="checkbox" wire:model.live="name.gregorian">{{ __('Year and month (YYYYMM)') }}</label>
        </div>
        @if ($builtName !== '')
            <div x-data="{ ok: false }" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px">
                <code dir="ltr" style="font-size:15px;padding:6px 10px;border-radius:8px;background:rgba(148,163,184,.12)">{{ $builtName }}</code>
                <button type="button" x-on:click="navigator.clipboard.writeText(@js($builtName)); ok = true; setTimeout(() => ok = false, 1500)" style="min-height:44px;padding:0 12px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent;font-size:13px"><span x-show="! ok">{{ __('Copy') }}</span><span x-show="ok" x-cloak>{{ __('Copied') }}</span></button>
                <button type="button" wire:click="useName" style="min-height:44px;padding:0 12px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent;font-size:13px">{{ __('Use as utm_campaign') }}</button>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
