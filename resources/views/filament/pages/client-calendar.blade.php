@php
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $dot = ['season' => '#0F766E', 'payday' => '#047857', 'school' => '#7A5AF8', 'campaign_end' => '#B54708', 'report' => '#1849A9', 'follow_up' => '#53389E', 'licence' => '#B42318', 'resume' => '#067647'];
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">
        <div><label for="cc-client" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">{{ __('Client') }}</label>
            <select id="cc-client" wire:model.live="clientId" style="{{ $field }}"><option value="">{{ __('All clients') }}</option>@foreach ($clients as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
        <div><label for="cc-days" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">{{ __('Period') }}</label>
            <select id="cc-days" wire:model.live="days" style="{{ $field }}">@foreach ([14, 30, 60, 90] as $d)<option value="{{ $d }}">{{ trans_choice('Next :n day|Next :n days', $d, ['n' => $d]) }}</option>@endforeach</select></div>
        <fieldset style="display:flex;flex-wrap:wrap;gap:6px 12px;border:0;padding:0;margin:0"><legend style="font-size:12px;font-weight:600;margin-bottom:4px">{{ __('Show') }}</legend>
            @foreach ($labels as $k => $l)<label style="display:flex;gap:6px;align-items:center;min-height:44px;font-size:13px"><input type="checkbox" wire:model.live="kinds" value="{{ $k }}"><span style="width:8px;height:8px;border-radius:999px;background:{{ $dot[$k] }}" aria-hidden="true"></span>{{ $l }}</label>@endforeach
        </fieldset>
    </div>

    @if ($events->isEmpty())
        <x-filament::section><p style="margin:0;opacity:.75">{{ __('Nothing on the calendar for this period.') }}</p></x-filament::section>
    @else
        <div style="display:grid;gap:10px" data-client-calendar>
            @foreach ($events as $date => $list)
                <x-filament::section>
                    <x-slot name="heading"><bdi>{{ \Carbon\Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('l j F') }}</bdi>@if ($date === $today) · {{ __('Today') }}@endif</x-slot>
                    <ul style="margin:0;padding:0;list-style:none;display:grid;gap:6px;font-size:14px">
                        @foreach ($list as $e)
                            <li style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><span style="width:8px;height:8px;border-radius:999px;background:{{ $dot[$e['kind']] }};flex:none" aria-hidden="true"></span>
                                <strong>{{ $labels[$e['kind']] }}</strong>
                                @if ($e['client'])<a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('view', ['record' => $e['client_id']]) }}" style="color:#0F766E;text-decoration:underline"><bdi>{{ $e['client'] }}</bdi></a>@endif
                                <span dir="auto">{{ $e['title'] }}</span></li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
