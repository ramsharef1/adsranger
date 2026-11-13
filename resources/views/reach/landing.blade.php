@php
    $h = fn (string $s) => \App\Support\Seo\ToolCopy::html($s, $ar);
    $t = fn (string $k, array $r = []) => $h(__('reach_ui.landing.'.$k, $r));
    $u = fn (string $p) => url(($ar ? '' : '/en').$p);
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('reach_ui.landing.title') }}</title>
<meta name="description" content="{{ __('reach_ui.landing.lead') }}">
@include('reach._style')
</head>
<body>
<main class="wrap">
    <p class="muted"><a href="{{ $u('/') }}" style="color:inherit"><bdi dir="ltr">AdsRanger</bdi></a> · <a href="{{ url(($ar ? '/en' : '').'/reach') }}" style="color:inherit">{{ $ar ? 'English' : 'العربية' }}</a></p>
    <h1>{!! $ar ? 'انشر عروضًا موثوقة واربح من زيارات موقعك وتطبيقك وقراءات بوتات الذكاء الاصطناعي، مع <bdi dir="ltr">AdsRanger Reach</bdi>' : 'Earn from your site, app and AI-agent reads with AdsRanger Reach' !!}</h1>
    <p class="muted" style="font-size:18px">{{ $t('lead') }}</p>
    <div class="note" role="note"><strong>{{ __('reach_ui.estimate_badge') }}</strong> · {{ $h(__('reach_ui.billing_awaits_launch')) }}</div>

    <div class="grid" style="margin-top:16px">
        @foreach (['tag', 'app', 'codes', 'ai', 'wallet', 'safe'] as $f)
            <div class="card"><h2 style="margin-top:0">{{ $t('f.'.$f.'.h') }}</h2><p class="muted">{{ $t('f.'.$f.'.p') }}</p></div>
        @endforeach
    </div>

    <h2>{{ $t('how') }}</h2>
    <ol>@foreach ([1, 2, 3] as $i)<li>{{ $t('step'.$i) }}</li>@endforeach</ol>

    @if ($ref)
        <p class="card">{{ $t('referred') }}</p>
    @endif
    <p style="margin-top:20px"><a class="btn" href="{{ url(($ar ? '' : '/en').'/reach/portal') }}">{{ $t('cta') }}</a></p>
    <p class="muted" style="font-size:14px">{{ $t('honest') }}</p>
</main>
</body>
</html>
