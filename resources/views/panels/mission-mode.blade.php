@if (\App\Support\MissionShell::on())
    @php
        $mode = \App\Support\MissionShell::mode();
        $action = route('filament.app.ui.prefs', ['tenant' => \Filament\Facades\Filament::getTenant()->slug]);
    @endphp
    {{-- Mission shell: simple mode shows fewer areas and the first-steps list; expert mode shows everything. --}}
    <form method="POST" action="{{ $action }}" class="mc-mode" role="group" aria-label="{{ __('Panel mode') }}" data-mission-mode>
        @csrf
        <button type="submit" name="mode" value="simple" aria-pressed="{{ $mode === 'simple' ? 'true' : 'false' }}">{{ __('Simple') }}</button>
        <button type="submit" name="mode" value="expert" aria-pressed="{{ $mode === 'expert' ? 'true' : 'false' }}">{{ __('Expert') }}</button>
    </form>
@endif
