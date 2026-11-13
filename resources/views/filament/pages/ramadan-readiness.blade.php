<x-filament-panels::page>
    @if (! $next)
        <x-filament::section><p style="margin:0">{{ __('No Ramadan date found in the next 13 months.') }}</p></x-filament::section>
    @else
        <div style="font-size:14px">{!! __('Ramadan starts on :date (in :n days).', ['date' => '<bdi>'.e(\Carbon\Carbon::parse($next['start'])->locale(app()->getLocale())->translatedFormat('j F Y')).'</bdi>', 'n' => $next['days']]) !!}
            @if ($next['estimated'])<span style="opacity:.7"> · {{ __('Estimated date; the start depends on the moon sighting.') }}</span>@endif</div>
        @if (! $rows)
            <x-filament::section><p style="margin:0">{{ __('Add a client first.') }}</p></x-filament::section>
        @else
            <div style="display:grid;gap:10px" data-ramadan-readiness>
                @foreach ($rows as $r)
                    <x-filament::section>
                        <x-slot name="heading"><a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('view', ['record' => $r['client']->id]) }}" style="color:#0F766E;text-decoration:underline"><bdi>{{ $r['client']->name }}</bdi></a>
                            · <span style="color:{{ $r['score'] >= 85 ? '#067647' : ($r['score'] >= 50 ? '#B54708' : '#B42318') }}">{{ $r['score'] }}%</span>
                            <span style="opacity:.7;font-weight:400"> · {{ __(':done of :total steps', ['done' => $r['done'], 'total' => $r['total']]) }}</span></x-slot>
                        <div role="progressbar" aria-valuenow="{{ $r['score'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Readiness') }}" style="height:8px;border-radius:999px;background:rgba(148,163,184,.25);overflow:hidden">
                            <div style="height:100%;width:{{ $r['score'] }}%;background:#0F766E"></div></div>
                        @if ($r['missing'])
                            <ul style="margin:8px 0 0;padding-inline-start:18px;font-size:13.5px">@foreach ($r['missing'] as $m)<li>{{ $m }}</li>@endforeach</ul>
                        @else
                            <p style="margin:8px 0 0;font-size:13.5px">{{ __('All steps done.') }}</p>
                        @endif
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
