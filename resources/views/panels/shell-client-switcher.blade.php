@if (\App\Support\Shell::on())
    @php
        $agency = \Filament\Facades\Filament::getTenant();
        $list = \App\Support\Shell::clientsWithStatus($agency);
        $currentId = \App\Support\CurrentClient::id($agency);
        $currentName = collect($list)->firstWhere('id', $currentId)['name'] ?? __('All clients');
        $dot = ['critical' => '#B42318', 'warning' => '#B54708', 'ok' => '#067647'];
        $levelLabel = ['critical' => __('Critical'), 'warning' => __('Warning'), 'ok' => __('No open alerts')];
        $go = fn ($v) => request()->fullUrlWithQuery(['client' => $v]);
    @endphp
    {{-- PX-05 (D-302): one client picker in the header that filters every page; "all clients" is the default. --}}
    <div class="ar-switch" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false" data-shell-client-switcher>
        <button type="button" class="ar-switch-btn" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true" aria-controls="ar-switch-list">
            <bdi>{{ $currentName }}</bdi>
            <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <ul id="ar-switch-list" class="ar-switch-list" x-show="open" x-cloak>
            <li><a href="{{ $go('all') }}" @if (! $currentId) aria-current="true" @endif>{{ __('All clients') }}</a></li>
            @foreach ($list as $c)
                <li><a href="{{ $go($c['id']) }}" @if ($currentId === $c['id']) aria-current="true" @endif>
                    <span class="ar-dot" style="background:{{ $dot[$c['level']] }}" role="img" aria-label="{{ $levelLabel[$c['level']] }}"></span><bdi>{{ $c['name'] }}</bdi></a></li>
            @endforeach
        </ul>
    </div>
@endif
