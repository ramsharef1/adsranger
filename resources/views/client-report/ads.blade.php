@php
    use App\Support\ResultResolver as R;
    $dir = $lang === 'ar' ? 'rtl' : 'ltr';
    $q = fn (array $over) => url()->current().'?'.http_build_query(array_merge(['p' => $period, 'lang' => $lang], $over));
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('report.ads_title', ['client' => $client->name]) }}</title>
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    @include('client-report._style')
</head>
<body>
<div class="wrap">
    <header>
        <div>
            <h1><bdi>{{ $client->name }}</bdi> · {{ __('report.ads') }}</h1>
            <div class="muted">{{ __('report.by', ['agency' => $agency->name]) }} · {{ __('report.range', ['from' => \Carbon\Carbon::parse($from)->locale($lang)->translatedFormat('j F Y'), 'to' => \Carbon\Carbon::parse($to)->locale($lang)->translatedFormat('j F Y')]) }}</div>
        </div>
        <a class="lang" href="{{ $q(['lang' => __('report.switch_lang')]) }}" lang="{{ __('report.switch_lang') }}">{{ __('report.switch') }}</a>
    </header>

    <nav class="pills" aria-label="{{ __('report.ads') }}">
        @foreach ($periods as $p)
            <a class="pill {{ $p === $period ? 'on' : '' }}" href="{{ $q(['p' => $p]) }}" @if ($p === $period) aria-current="page" @endif>{{ __("report.periods.$p") }}</a>
        @endforeach
    </nav>
    <p class="muted">{{ __('report.ads_intro') }}</p>

    @if (! count($ads))
        <div class="card">{{ __('report.no_ads') }}</div>
    @else
        <div class="ads">
            @foreach ($ads as $ad)
                @php $net = $ad['network_name'] ?? __('report.this_network'); @endphp
                <div class="card ad">
                    @if ($ad['thumbnail'])
                        <img src="{{ $ad['thumbnail'] }}" alt="{{ $ad['name'] }}" loading="lazy" referrerpolicy="no-referrer">
                    @else
                        <div class="ph" role="img" aria-label="{{ __('report.no_image', ['network' => $net]) }}">{{ __('report.no_image', ['network' => $net]) }}</div>
                    @endif
                    <div class="name"><bdi>{{ $ad['name'] }}</bdi></div>
                    @if ($ad['network_name'])
                        <div class="muted"><bdi>{{ $ad['network_name'] }}</bdi></div>
                    @endif
                    <dl>
                        <dt>{{ R::label($ad['main_type'], $lang) ?: __('report.results') }}</dt><dd>{{ number_format($ad['main_results']) }}</dd>
                        @if (! $hideSpend)
                            <dt>{{ __('report.cpr') }}</dt><dd><bdi dir="ltr">{{ R::money($ad['cpr'], $ad['currency']) }}</bdi></dd>
                        @endif
                        <dt>{{ __('report.ctr') }}</dt><dd><bdi dir="ltr">{{ $ad['ctr'] !== null ? number_format($ad['ctr'], 2).'%' : '–' }}</bdi></dd>
                    </dl>
                </div>
            @endforeach
        </div>
    @endif

    <p><a class="pill" href="{{ url('/r/'.$link->token).'?'.http_build_query(['p' => $period, 'lang' => $lang]) }}">{{ __('report.back') }}</a></p>

    <footer>
        {{ __('report.footer') }}<br>
        <span dir="ltr">AdsRanger</span>
    </footer>
</div>
</body>
</html>
