@php
    $ar = $lang === 'ar';
    $base = $ar ? '' : '/en';
    $titles = [
        'privacy' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
        'terms' => ['ar' => 'شروط الاستخدام', 'en' => 'Terms of Service'],
        'data-deletion' => ['ar' => 'حذف البيانات', 'en' => 'Data Deletion'],
    ];
    $title = $titles[$page][$lang];
    $other = ($ar ? '/en' : '').'/'.$page;
    $updated = $ar ? 'آخر تحديث: 4 نوفمبر 2026' : 'Last updated: 4 November 2026';
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} · AdsRanger</title>
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<link rel="alternate" hreflang="ar" href="https://adsranger.com/{{ $page }}">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/{{ $page }}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#2DD4BF;--line:#232c4d}
*{box-sizing:border-box}
body{margin:0;font-family:{!! $ar ? '"IBM Plex Sans Arabic",' : '' !!}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.9' : '1.65' }}}
.wrap{max-width:820px;margin:0 auto;padding:0 20px}
header{display:flex;justify-content:space-between;align-items:center;padding:20px 0;border-bottom:1px solid var(--line)}
.logo{display:flex;align-items:center;gap:10px;color:var(--ink);text-decoration:none;font-weight:700;direction:ltr}
.logo img{width:30px;height:30px}.logo b{color:var(--acc)}
.lang{color:var(--muted);text-decoration:none;font-size:14px;border:1px solid var(--line);padding:5px 12px;border-radius:99px}
main{padding:36px 0 48px}
h1{font-size:30px;margin:0 0 6px}
.updated{color:var(--muted);font-size:14px;margin:0 0 28px}
h2{font-size:19px;margin:30px 0 8px;color:var(--acc)}
p,li{color:#d6dbeb;font-size:16px}
ul,ol{padding-inline-start:22px}
li{margin:4px 0}
a{color:var(--acc)}
main a[href^="mailto:"]{display:inline-flex;align-items:center;min-height:44px}
.note{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin:18px 0}
footer{border-top:1px solid var(--line);padding:22px 0;color:var(--muted);font-size:13px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px}
footer a{color:var(--muted);margin-inline-end:14px}
</style>
</head>
<body>
<div class="wrap">
@include('public.nav', ['lang' => $lang, 'switch_url' => $other])
<main>
    <h1>{{ $title }}</h1>
    <p class="updated">{{ $updated }}</p>
    @include('legal.'.$page.'-'.$lang)
</main>
@include('public.footer', ['lang' => $ar ? 'ar' : 'en'])
</div>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
