@php
    $ar = $lang === 'ar';
    $bdi = fn (?string $v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
    $args = ['client' => $bdi($s->client->name), 'email' => $bdi($email), 'agency' => $bdi($s->agency->name)];
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('report.unsub_title') }} · AdsRanger</title>
    <style>
        :root { --bg:#F5F7FB; --card:#fff; --text:#0B1020; --muted:#4A5473; --btn:#0F766E; --border:#E5E8F0; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0B1020; --card:#121933; --text:#E6E9F2; --muted:#9AA3C0; --border:#263056; } }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'IBM Plex Sans Arabic',Tahoma,Arial,sans-serif; }
        main { max-width:520px; margin:48px auto; padding:0 16px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; line-height:1.8; }
        button { background:var(--btn); color:#fff; border:0; border-radius:8px; padding:12px 22px; font:inherit; font-weight:600; min-height:44px; cursor:pointer; }
        .muted { color:var(--muted); font-size:14px; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1 style="font-size:20px;margin:0 0 12px">{{ __('report.unsub_title') }}</h1>
        @if ($done)
            <p>{!! __('report.unsub_done', $args) !!}</p>
            <p class="muted">{!! __('report.unsub_note', $args) !!}</p>
        @else
            <p>{!! __('report.unsub_question', $args) !!}</p>
            <form method="post" action="{{ request()->fullUrl() }}">
                @csrf
                <button type="submit">{{ __('report.unsub_button') }}</button>
            </form>
        @endif
    </div>
    <p class="muted" style="text-align:center"><bdi dir="ltr">AdsRanger</bdi></p>
</main>
</body>
</html>
