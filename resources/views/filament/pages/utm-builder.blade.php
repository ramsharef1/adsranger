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
    <div style="{{ $muted }}">{{ __('Add tracking to a landing page address so GA4 and your reports show which network, campaign and ad brought each visit.') }}</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:16px;align-items:start">
        <x-filament::section>
            <x-slot name="heading">{{ __('Build a link') }}</x-slot>
            <div style="display:grid;gap:12px">
                <div>
                    <label for="utm-base" style="{{ $label }}">{{ __('Landing page address') }}</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <input id="utm-base" type="url" wire:model.live.debounce.400ms="baseUrl" placeholder="https://example.com/offer" dir="ltr" style="{{ $field }};flex:1;min-width:200px">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-signal" wire:click="checkLink" wire:loading.attr="disabled" wire:target="checkLink">{{ __('Check page') }}</x-filament::button>
                    </div>
                    @error('baseUrl')<div style="color:#dc2626;font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
                    @if ($invalid)<div style="color:#dc2626;font-size:12px;margin-top:4px">{{ __('Enter a full http(s) address.') }}</div>@endif
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
                        <code id="utm-link-{{ $i }}" dir="ltr" style="flex:1;min-width:0;font-size:12px;word-break:break-all;text-align:left">{{ $link }}</code>
                        <x-filament::button size="xs" color="gray" icon="heroicon-o-clipboard"
                            x-on:click="navigator.clipboard.writeText(@js($link)).then(() => { copied = true; setTimeout(() => copied = false, 1500) }).catch(() => { const r = document.createRange(); r.selectNodeContents(document.getElementById('utm-link-{{ $i }}')); const s = window.getSelection(); s.removeAllRanges(); s.addRange(r) })">
                            <span x-show="! copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                        </x-filament::button>
                    </div>
                @empty
                    <div style="{{ $muted }}">{{ __('Enter a landing page address to see the tracked link.') }}</div>
                @endforelse

                @if (count($warnings))
                    <ul style="margin:10px 0 0;padding-inline-start:18px;display:grid;gap:4px;font-size:12px;color:#b45309">
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
                        <div style="font-size:12px;margin-top:6px;color:#b45309">{{ __('Redirects can drop UTM parameters. Use the final address as the landing page if you can.') }}</div>
                    @endif
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
