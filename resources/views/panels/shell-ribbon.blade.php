@if (\App\Support\Shell::on())
    @php
        $r = \App\Support\Shell::ribbon(\Filament\Facades\Filament::getTenant());
    @endphp
    {{-- PX-01 (D-302): the season line under the header: today in both calendars and the next season. --}}
    <div class="ar-ribbon" data-shell-ribbon role="note">
        <span>{{ $r['date'] }}@if ($r['hijri']) · {{ $r['hijri'] }}@endif</span>
        @if ($r['next'])
            <span>{{ trans_choice(':season in :n day|:season in :n days', $r['next']['days'], ['season' => $r['next']['label'], 'n' => $r['next']['days']]) }}
                · <a href="{{ \App\Filament\Pages\MenaCalendarPage::getUrl() }}">{{ __('Plan for it') }}</a></span>
        @endif
    </div>
@endif
