@php
    // UX-9: branded, Arabic-first error pages. Language by URL prefix (/en → English), else the panel choice, else Arabic.
    $path = trim(request()->path(), '/');
    $loc = ($path === 'en' || str_starts_with($path, 'en/')) ? 'en' : (rescue(fn () => session('locale'), null, false) ?: 'ar');
    $loc = in_array($loc, ['ar', 'en'], true) ? $loc : 'ar';
    $dir = $loc === 'ar' ? 'rtl' : 'ltr';
    $t = fn (string $k) => __("ui.errors.$k", [], $loc);
    $home = $loc === 'en' ? url('/en') : url('/');
    $ref = $showRef ?? false ? app(\App\Support\ErrorRef::class)->id() : null;
@endphp
<!DOCTYPE html>
<html lang="{{ $loc }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $t("$code.title") }} · AdsRanger</title>
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #ffffff; --fg: #0f172a; --muted: #475569; --card: #f8fafc; --line: #e2e8f0; --btn: #0F766E; --btn-fg: #ffffff; --link: #0F766E; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1020; --fg: #f1f5f9; --muted: #a3aec2; --card: #121933; --line: #24304f; --link: #5EEAD4; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--fg); font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif; padding: 24px 16px; }
        main { width: 100%; max-width: 520px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 32px 24px; text-align: center; }
        .code { font-size: 14px; color: var(--muted); margin: 18px 0 4px; direction: ltr; }
        h1 { font-size: 22px; margin: 0 0 10px; line-height: 1.5; }
        p { color: var(--muted); line-height: 1.8; margin: 0 0 20px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .btn { display: inline-flex; align-items: center; min-height: 44px; padding: 0 18px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 15px; }
        .btn-primary { background: var(--btn); color: var(--btn-fg); }
        .btn-link { color: var(--link); border: 1px solid var(--line); }
        .ref { margin-top: 22px; font-size: 13px; color: var(--muted); }
        .ref bdi { font-family: ui-monospace, monospace; color: var(--fg); }
    </style>
</head>
<body>
<main>
    <div aria-hidden="true">@include('brand.logo')</div>
    <div class="code">{{ $code }}</div>
    <h1>{{ $t("$code.title") }}</h1>
    <p>{{ $t("$code.body") }}</p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ $home }}">{{ $t('home') }}</a>
        <a class="btn btn-link" href="{{ url('/app') }}">{{ $t('app') }}</a>
    </div>
    @if ($ref)
        <div class="ref">{{ $t('ref') }}: <bdi dir="ltr">{{ $ref }}</bdi><br>{{ $t('ref_note') }}</div>
    @endif
</main>
</body>
</html>
