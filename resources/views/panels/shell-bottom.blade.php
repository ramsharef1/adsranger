@if (\App\Support\Shell::on())
    @php
        $agency = \Filament\Facades\Filament::getTenant();
        $current = \App\Support\Shell::current();
        $links = \App\Support\Shell::areaLinks();
        $alertsUrl = \App\Filament\Resources\Alerts\AlertResource::getUrl('index');
        $onAlerts = request()->routeIs(\App\Filament\Resources\Alerts\AlertResource::getRouteBaseName().'.*');
        $open = \App\Models\Alert::where('agency_id', $agency->id)->where('status', 'open')
            ->whereIn('client_id', \App\Models\Client::visibleTo(auth()->user(), $agency)->select('id'))->count();
    @endphp
    {{-- PX-01 (D-302, UI/UX 18:08): phone bottom bar Today · Clients · Ads · Alerts · More. --}}
    <nav class="ar-bottom" aria-label="{{ __('Main navigation') }}" data-shell-bottom>
        <a href="{{ $links['today'] ?? \App\Filament\Pages\DailyDesk::getUrl() }}" @if ($current === 'today' && ! $onAlerts) aria-current="page" @endif>{{ __('Today') }}</a>
        @isset($links['clients'])<a href="{{ $links['clients'] }}" @if ($current === 'clients') aria-current="page" @endif>{{ __('Clients') }}</a>@endisset
        @isset($links['ads'])<a href="{{ $links['ads'] }}" @if ($current === 'ads') aria-current="page" @endif>{{ __('Ads') }}</a>@endisset
        <a href="{{ $alertsUrl }}" @if ($onAlerts) aria-current="page" @endif aria-label="{{ trans_choice('Alerts, :n open|Alerts, :n open', $open, ['n' => $open]) }}">{{ __('Alerts') }}@if ($open)<span class="ar-count" aria-hidden="true">{{ $open > 99 ? '99+' : $open }}</span>@endif</a>
        <button type="button" x-data x-on:click="$store.sidebar.open()" aria-controls="fi-main-sidebar">{{ __('More') }}</button>
    </nav>
@endif
