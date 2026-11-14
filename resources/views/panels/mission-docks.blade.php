@if (\App\Support\MissionShell::on())
    @php
        $agency = \Filament\Facades\Filament::getTenant();
        $rail = \App\Support\MissionShell::rail($agency);
        $next = \App\Support\MissionShell::nextStep($agency);
        $dock = \App\Support\MissionShell::dock($agency);
        $simple = \App\Support\MissionShell::mode() === 'simple';
        $coach = $simple ? \App\Support\MissionShell::coach($agency) : [];
        $r = \App\Support\Shell::ribbon($agency);
        $dot = ['critical' => '#F87171', 'warning' => '#FBBF24', 'ok' => '#34D399'];
        $levelLabel = ['critical' => __('Critical'), 'warning' => __('Warning'), 'ok' => __('No open alerts'), 'info' => __('Info')];
        $firstLocked = collect($rail)->search(fn ($i) => in_array($i['key'], array_keys(\App\Support\MissionShell::addOns()), true));
    @endphp

    {{-- Icon rail: areas first, then the add-ons (locked ones lead to Plan & usage). --}}
    <nav class="mc-rail" aria-label="{{ __('Areas and add-ons') }}" data-mission-rail>
        @foreach ($rail as $i => $item)
            @if ($firstLocked !== false && $i === $firstLocked)<hr aria-hidden="true">@endif
            <a href="{{ $item['url'] }}" @if ($item['current']) aria-current="page" @endif @if ($item['locked']) data-locked title="{{ __(':name: add-on, not switched on yet', ['name' => $item['label']]) }}" @endif>
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                <span>{{ $item['label'] }}</span>
                @if ($item['locked'])
                    <svg class="mc-lock" aria-hidden="true" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 11h12v9H6zM9 11V8a3 3 0 016 0v3"/></svg>
                    <span class="mc-sr">{{ __('add-on, not switched on yet') }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    {{-- Next-step panel: the most severe open alert, shortcuts, and the first steps in simple mode. --}}
    <div x-data="{ open: false }" x-on:keydown.escape.window="open = false" data-mission-next>
        <button type="button" class="mc-next-btn" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="mc-next">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/></svg>
            {{ __('Next step') }}@if ($next) <span class="mc-chip mc-{{ $next['level'] }}">1</span>@endif
        </button>
        <aside id="mc-next" class="mc-next" x-show="open" x-cloak x-on:click.outside="open = false" aria-label="{{ __('Next step') }}">
            <h2>{{ __('Next step') }}</h2>
            <div class="mc-card">
                @if ($next)
                    <span class="mc-chip mc-{{ $next['level'] }}">{{ $levelLabel[$next['level']] }}</span>
                    <div style="font-weight:600;margin-top:8px">{{ $next['title'] }}</div>
                    <div class="mc-mu"><bdi>{{ $next['client'] }}</bdi></div>
                    <a class="mc-go" href="{{ $next['url'] }}">{{ __('Open it') }}</a>
                @else
                    <span class="mc-chip mc-ok">{{ __('All clear') }}</span>
                    <div class="mc-mu" style="margin-top:8px">{{ __('No open alerts on the clients you see.') }}</div>
                @endif
            </div>
            @if ($simple)
                <h2>{{ __('First steps') }}</h2>
                <ul style="margin-bottom:12px">
                    @foreach ($coach as $step)
                        <li><a href="{{ $step['url'] }}"><span class="mc-chip {{ $step['done'] ? 'mc-ok' : 'mc-info' }}">{{ $step['done'] ? __('Done') : __('To do') }}</span>{{ $step['label'] }}</a></li>
                    @endforeach
                </ul>
            @endif
            <h2>{{ __('Shortcuts') }}</h2>
            <ul>
                <li><a href="{{ \App\Filament\Resources\Alerts\AlertResource::getUrl('index') }}">{{ __('Open alerts') }}</a></li>
                <li><a href="{{ \App\Filament\Pages\PacingBoard::getUrl() }}">{{ __('Spend pacing') }}</a></li>
                <li><a href="{{ \App\Filament\Pages\MenaCalendarPage::getUrl() }}">{{ __('Seasons calendar') }}</a></li>
            </ul>
            <p class="mc-mu" style="margin:12px 0 0">{{ $simple ? __('Simple mode shows fewer areas. Switch to Expert in the top bar to see everything.') : __('Expert mode shows every area. Switch to Simple in the top bar for a shorter panel.') }}</p>
        </aside>
    </div>

    {{-- Desktop dock: the clients that need attention first, then the status sentence. --}}
    <div class="mc-dock" data-mission-dock>
        @foreach ($dock as $c)
            <a href="{{ $c['url'] }}"><span class="mc-dot" style="background:{{ $dot[$c['level']] }}" role="img" aria-label="{{ $levelLabel[$c['level']] }}"></span><bdi>{{ $c['name'] }}</bdi></a>
        @endforeach
        @if ($r['clients'])
            <span class="mc-dock-status">{{ __(':calm of :total clients without open Critical or Warning alerts', ['calm' => $r['calm'], 'total' => $r['clients']]) }}</span>
        @endif
    </div>
@endif
