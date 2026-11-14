@if (\App\Support\AdminShell::on())
    @php
        $current = \App\Support\AdminShell::current();
        $labels = \App\Support\AdminShell::labels();
        $w = fn ($v): string => (string) \App\Support\TextClean::western((string) $v);
    @endphp
    {{-- E2-068: /admin PX area switcher rail (Overview · Growth · Platform · Guards · All). --}}
    <nav class="ar-adm-areas" aria-label="{{ __('Platform areas') }}" data-admin-shell-areas>
        @foreach (\App\Support\AdminShell::areaNavigation() as $area => $meta)
            <a href="{{ $meta['url'] }}" data-admin-area="{{ $area }}" @if ($area === $current) aria-current="page" @endif>
                <span>{{ $labels[$area] ?? $area }}</span>
                <span class="ar-adm-count" aria-hidden="true">{{ $w($meta['count']) }}</span>
            </a>
        @endforeach
    </nav>
@endif
