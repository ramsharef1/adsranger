@if (\App\Support\Shell::on())
    @php
        $r = \App\Support\Shell::ribbon(\Filament\Facades\Filament::getTenant());
    @endphp
    {{-- PX-03 (D-302): the season line under the header: today in both calendars, the next 2 seasons and one status sentence. --}}
    <div class="ar-ribbon" data-shell-ribbon role="note">
        <span>{{ $r['date'] }}@if ($r['hijri']) · {{ $r['hijri'] }}@endif</span>
        @foreach ($r['next'] as $i => $n)
            <span>{{ trans_choice(':season in :n day|:season in :n days', $n['days'], ['season' => $n['label'], 'n' => $n['days']]) }}@if ($i === 0) · <a href="{{ \App\Filament\Pages\MenaCalendarPage::getUrl() }}">{{ __('Plan for it') }}</a>@endif</span>
        @endforeach
        @if ($r['clients'])
            <span class="ar-ribbon-status" data-shell-status>
                {{ __(':calm of :total clients without open Critical or Warning alerts', ['calm' => $r['calm'], 'total' => $r['clients']]) }}@if ($r['critical'] || $r['warning']) · {{ __('Critical :c · Warning :w', ['c' => $r['critical'], 'w' => $r['warning']]) }}@endif
            </span>
        @endif
    </div>
@endif
