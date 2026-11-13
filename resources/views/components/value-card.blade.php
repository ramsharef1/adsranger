@props(['card'])
{{-- PS-29 / NB-20 / NB-21 / PS-31 (D-306): what AdsRanger did this month; hours are an estimate with the minutes shown. --}}
<x-filament::section data-value-card>
    <x-slot name="heading">{{ __('What AdsRanger did for you this month') }}</x-slot>
    <x-slot name="description">{!! __('About :hours hours saved («تقدير»: count × minutes per task, shown below).', ['hours' => '<bdi>'.e(number_format($card['hours'], 1)).'</bdi>']) !!}</x-slot>
    <ul style="margin:0;padding:0;list-style:none;display:grid;gap:4px;font-size:14px">
        @foreach ($card['items'] as $it)
            <li style="display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid rgba(148,163,184,.2);padding:4px 0">
                <span>{{ \App\Support\ValueCard::labels()[$it['key']] }}</span>
                <span><strong>{{ number_format($it['count']) }}</strong> <span style="opacity:.7">× {{ trans_choice(':n minute|:n minutes', $it['minutes'], ['n' => $it['minutes']]) }}</span></span>
            </li>
        @endforeach
    </ul>
</x-filament::section>
