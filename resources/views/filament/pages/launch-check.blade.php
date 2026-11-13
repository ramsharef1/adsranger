@php
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:14px;width:100%';
    $icon = ['ok' => '✓', 'critical' => '✗', 'warning' => '⚠', 'info' => 'ℹ'];
    $color = ['ok' => '#067647', 'critical' => '#B42318', 'warning' => '#B54708', 'info' => '#1849A9'];
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">{{ __('Paste the landing page link (with its UTM tags) and the ad text. AdsRanger checks them and lists what to fix; nothing is changed or launched.') }}</x-slot>
        <form wire:submit="check" style="display:grid;gap:12px">
            <div><label for="lc-client" style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">{{ __('Client') }}</label>
                <select id="lc-client" wire:model="clientId" style="{{ $field }}">@foreach ($clients as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
            <div><label for="lc-url" style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">{{ __('Landing page link') }}</label>
                <input id="lc-url" type="url" wire:model="url" style="{{ $field }}" dir="ltr" placeholder="https://example.com/offer?utm_source=meta&utm_medium=paid&utm_campaign=…">
                @error('url')<div style="color:#B42318;font-size:13px">{{ $message }}</div>@enderror</div>
            <div><label for="lc-ad" style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">{{ __('Ad text') }}</label>
                <textarea id="lc-ad" wire:model="adText" rows="4" style="{{ $field }};min-height:100px" dir="auto"></textarea>
                @error('adText')<div style="color:#B42318;font-size:13px">{{ $message }}</div>@enderror</div>
            <div><x-filament::button type="submit" style="min-height:44px"><span wire:loading.remove wire:target="check">{{ __('Check') }}</span><span wire:loading wire:target="check">{{ __('Checking…') }}</span></x-filament::button></div>
        </form>
    </x-filament::section>

    @if ($results !== null)
        <x-filament::section data-launch-results>
            <x-slot name="heading">{{ __(':n to fix', ['n' => collect($results)->whereIn('level', ['critical', 'warning'])->count()]) }}</x-slot>
            <ul style="margin:0;padding:0;list-style:none;display:grid;gap:8px;font-size:14px">
                @foreach ($results as $r)
                    <li style="display:flex;gap:10px;align-items:flex-start"><span aria-hidden="true" style="color:{{ $color[$r['level']] }};font-weight:700;min-width:16px">{{ $icon[$r['level']] }}</span>
                        <span><span class="sr-only">{{ ['ok' => __('Passed'), 'critical' => __('Critical'), 'warning' => __('Warning'), 'info' => __('Info')][$r['level']] }}:</span> {{ $r['message'] }}</span></li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
