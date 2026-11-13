@php $f = 'min-height:44px;padding:4px 8px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px'; @endphp
<x-filament-panels::page>
    <x-filament::section>
        <p style="margin:0;font-size:14px;line-height:1.7">{{ __('Hijri dates are calculated and shown as expected. When a country announces the moon sighting, set the first day, mark it confirmed and add the source. Every calendar, pacing hint and season review uses it.') }}</p>
    </x-filament::section>
    <x-filament::section data-holiday-status>
        <div style="overflow-x:auto"><table style="width:100%;font-size:14px;border-collapse:collapse">
            <thead><tr><th style="text-align:start;padding:8px">{{ __('Holiday') }}</th><th style="text-align:start;padding:8px">{{ __('First day') }}</th><th style="text-align:start;padding:8px">{{ __('Status') }}</th><th style="text-align:start;padding:8px">{{ __('Source') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($events as $e)
                    @php $k = $e['key'].'|'.substr($e['calc_start'], 0, 4); @endphp
                    <tr style="border-top:1px solid rgba(148,163,184,.25)">
                        <td style="padding:8px">{{ $ar ? $e['ar'] : $e['label'] }}<div style="font-size:12px;opacity:.65">{{ __('Calculated') }}: <bdi dir="ltr">{{ $e['calc_start'] }}</bdi></div></td>
                        <td style="padding:8px"><input type="date" wire:model="rows.{{ $k }}.start_date" aria-label="{{ __('First day') }}" style="{{ $f }}"></td>
                        <td style="padding:8px"><select wire:model="rows.{{ $k }}.status" aria-label="{{ __('Status') }}" style="{{ $f }}"><option value="expected">{{ __('Expected') }}</option><option value="confirmed">{{ __('Confirmed') }}</option></select></td>
                        <td style="padding:8px"><input wire:model="rows.{{ $k }}.source" maxlength="255" aria-label="{{ __('Source') }}" style="{{ $f }};width:100%"></td>
                        <td style="padding:8px"><button type="button" wire:click="save('{{ $k }}')" style="{{ $f }}">{{ __('Save') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </x-filament::section>
</x-filament-panels::page>
