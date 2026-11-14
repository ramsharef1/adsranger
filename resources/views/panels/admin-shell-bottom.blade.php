@if (\App\Support\AdminShell::on())
    @php
        $current = \App\Support\AdminShell::current();
        $labels = \App\Support\AdminShell::labels();
        $nav = \App\Support\AdminShell::areaNavigation();
    @endphp
    {{-- E2-068: mobile bottom bar (<=900px) with >=44px touch targets. --}}
    <nav class="ar-adm-bottom" aria-label="{{ __('Main navigation') }}" data-admin-shell-bottom>
        @foreach (\App\Support\AdminShell::AREAS as $area)
            @if (isset($nav[$area]))
                <a href="{{ $nav[$area]['url'] }}" @if ($current === $area) aria-current="page" @endif>{{ $labels[$area] }}</a>
            @endif
        @endforeach
        <button type="button" x-data x-on:click="$store.sidebar.open()" aria-controls="fi-main-sidebar">{{ __('More') }}</button>
    </nav>
@endif
