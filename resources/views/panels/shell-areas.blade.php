@if (\App\Support\Shell::on())
    @php
        $current = \App\Support\Shell::current();
        $labels = \App\Support\Shell::labels();
    @endphp
    {{-- PX-01 (D-302): the header switches area; the side menu shows that area's pages. --}}
    <nav class="ar-areas" aria-label="{{ __('Platform areas') }}" data-shell-areas>
        @foreach (\App\Support\Shell::areaLinks() as $area => $url)
            <a href="{{ $url }}" @if ($area === $current) aria-current="page" @endif>{{ $labels[$area] }}</a>
        @endforeach
    </nav>
@endif
