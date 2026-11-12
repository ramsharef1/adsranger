@php
    /*
     * One public layout (Website P-5 + P-0): head + SEO, skip link, header with menu and theme toggle, <main>, footer,
     * cookie notice. Child views set $lang, $arPath, $title, $meta and optionally $schema (list of JSON-LD arrays),
     * then fill @section('content') and @push('styles').
     */
    $ar = ($lang ?? 'ar') !== 'en';
    $lang = $ar ? 'ar' : 'en';
    $arPath = $arPath ?? \App\Support\PublicUrl::arPathOf(request()->path());
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    // UX-226: the Latin brand inside Arabic menu / footer text gets its own direction.
    $bd = fn (string $t) => (string) \App\Support\Seo\ToolCopy::html($t, $ar);
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $arUrl = \App\Support\PublicUrl::absolute($arPath, 'ar');
    $enUrl = \App\Support\PublicUrl::absolute($arPath, 'en');
    $switchUrl = \App\Support\PublicUrl::path($arPath, $ar ? 'en' : 'ar');
    $site = \App\Support\PublicSite::class;
    $menu = $site::menu($ar);
    $isHome = $arPath === '/';
    $related = $site::related($arPath);
    $closing = $site::hasClosingBand($arPath);
    $sticky = ! in_array($arPath, ['/waitlist', '/partners'], true);
    $crumb = $isHome ? null : $site::name($arPath, $ar);
    $waitlistLabel = $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist';
    $schema = $schema ?? [];
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $meta }}">
@if (! empty($noindex))<meta name="robots" content="noindex, nofollow">
@endif
<link rel="canonical" href="{{ $selfUrl }}">
<link rel="alternate" hreflang="ar" href="{{ $arUrl }}">
<link rel="alternate" hreflang="en" href="{{ $enUrl }}">
<link rel="alternate" hreflang="x-default" href="{{ $arUrl }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="AdsRanger">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $meta }}">
<meta property="og:url" content="{{ $selfUrl }}">
@php($shareImage = \App\Support\ShareCard::imageUrl($arPath, $ar))
@if ($shareImage)
<meta property="og:image" content="{{ $shareImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ \App\Support\ShareCard::card($arPath, $ar)['headline'] }}">
<meta name="twitter:image" content="{{ $shareImage }}">
@else
<meta property="og:image" content="https://adsranger.com/brand/icon-512.png">
@endif
<meta property="og:locale" content="{{ $ar ? 'ar_AR' : 'en_US' }}">
<meta property="og:locale:alternate" content="{{ $ar ? 'en_US' : 'ar_AR' }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700{{ $ar ? '' : '&family=IBM+Plex+Sans:wght@400;600;700' }}&display=optional" rel="stylesheet">
@foreach ($schema as $block)
<script type="application/ld+json">{!! json_encode(['@'.'context' => 'https://schema.org'] + $block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endforeach
<script>(function(){try{var t=localStorage.getItem('ar_theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
<style>
:root{--bg:#ffffff;--surface:#f8fafc;--card:#ffffff;--ink:#0b1120;--muted:#475569;--acc:#0f766e;--line:#e2e8f0;--field:#6b7280;--btn:#0f766e;--btn-ink:#ffffff;--info-bg:#e6f4f1;--info-ink:#0f766e;--neu-bg:#f1f5f9;--neu-ink:#334155;--bad:#b91c1c;color-scheme:light}
:root[data-theme=dark]{--bg:#0b1120;--surface:#111827;--card:#111827;--ink:#f8fafc;--muted:#94a3b8;--acc:#5eead4;--line:#1e293b;--field:#64748b;--btn:#5eead4;--btn-ink:#0b1120;--info-bg:rgba(94,234,212,.12);--info-ink:#5eead4;--neu-bg:#1e293b;--neu-ink:#cbd5e1;--bad:#fca5a5;color-scheme:dark}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]){--bg:#0b1120;--surface:#111827;--card:#111827;--ink:#f8fafc;--muted:#94a3b8;--acc:#5eead4;--line:#1e293b;--field:#64748b;--btn:#5eead4;--btn-ink:#0b1120;--info-bg:rgba(94,234,212,.12);--info-ink:#5eead4;--neu-bg:#1e293b;--neu-ink:#cbd5e1;--bad:#fca5a5;color-scheme:dark}}
*{box-sizing:border-box}
html{overflow-x:clip}
body{margin:0;background:var(--bg);color:var(--ink);font-family:{!! $ar ? '"IBM Plex Sans Arabic",system-ui,sans-serif' : '"IBM Plex Sans","IBM Plex Sans Arabic",system-ui,sans-serif' !!};line-height:{{ $ar ? '1.8' : '1.6' }};font-size:16px}
a{color:var(--acc)}
:focus-visible{outline:2px solid var(--acc);outline-offset:2px}
.wrap{max-width:1200px;margin-inline:auto;padding-inline:16px}
@media (min-width:768px){.wrap{padding-inline:24px}}
.skip{position:absolute;inset-inline-start:8px;top:-60px;z-index:100;background:var(--btn);color:var(--btn-ink);padding:10px 16px;border-radius:8px;min-height:44px;display:inline-flex;align-items:center;text-decoration:none;font-weight:600}
.skip:focus{top:8px}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 18px;border-radius:10px;background:var(--btn);color:var(--btn-ink);text-decoration:none;font-weight:600;border:0;cursor:pointer;font:inherit;font-weight:600}
.btn{transition:transform .12s ease,opacity .12s ease}
.btn:hover{opacity:.92}
.btn:active{transform:translateY(1px)}
.btn-ghost{background:transparent;color:var(--ink);border:1px solid var(--field)}
.btn-ghost:hover{background:var(--neu-bg)}
.badge{display:inline-block;font-size:12.5px;font-weight:600;padding:2px 10px;border-radius:99px;background:var(--info-bg);color:var(--info-ink)}
.badge-neutral{background:var(--neu-bg);color:var(--neu-ink)}
.muted{color:var(--muted)}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px}
main{display:block;padding-block:0 8px}
main>.wrap{padding-top:24px}
main p a,main li a,main td a{text-decoration:underline}
h1{font-size:clamp(28px,4.5vw,42px);line-height:1.25;margin:0 0 12px}
h2{font-size:clamp(21px,3vw,26px);line-height:1.35;margin:32px 0 12px}
h3{font-size:18px;margin:0 0 6px}
/* header: dark bar + grouped menu (D-234) */
.sh{position:relative;z-index:40;background:#0b1120;color:#f8fafc;border-bottom:1px solid #1e293b}
.sh-in{display:flex;align-items:center;gap:8px;min-height:68px}
.sh-logo{display:inline-flex;align-items:center;gap:8px;min-height:44px;min-width:44px;direction:ltr;color:#fff;text-decoration:none;font-weight:700;font-size:19px}
.sh-logo img{width:32px;height:32px}
.sh-logo b{color:#5eead4}
.sh-nav{display:flex;align-items:center;gap:2px;margin-inline-start:20px}
.sh-nav>a,.mm>button,.sh-ctl{white-space:nowrap;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:44px;min-width:44px;padding:6px 12px;border-radius:8px;color:#cbd5e1;text-decoration:none;font-size:15px;font-weight:500;background:transparent;border:0;font-family:inherit;cursor:pointer}
.sh-nav>a[aria-current=page],.mm.cur>button{color:#fff;font-weight:700}
.sh-nav>a:hover,.mm>button:hover,.mm>button[aria-expanded=true],.sh-ctl:hover{color:#fff;background:rgba(255,255,255,.08)}
.mm{position:relative}
.mm>button svg{width:12px;height:12px;transition:transform .15s}
.mm>button[aria-expanded=true] svg{transform:rotate(180deg)}
.mm-panel{display:none;position:absolute;top:calc(100% + 8px);inset-inline-start:0;min-width:340px;background:var(--card);color:var(--ink);border:1px solid var(--line);border-radius:14px;box-shadow:0 24px 48px -16px rgba(2,6,23,.45);padding:8px}
.mm>button[aria-expanded=true]+.mm-panel{display:block}
.mm-panel a{display:flex;flex-direction:column;gap:2px;padding:10px 12px;border-radius:10px;color:var(--ink);text-decoration:none;min-height:44px}
.mm-panel a strong{font-size:15px}
.mm-panel a span{font-size:13px;color:var(--muted)}
.mm-panel a:hover,.mm-panel a:focus-visible{background:var(--surface)}
.mm-panel a[aria-current=page] strong{color:var(--acc)}
.sh-end{display:flex;align-items:center;gap:6px;margin-inline-start:auto}
.sh-end .btn{white-space:nowrap;padding:8px 18px;border-radius:10px;font-weight:700;background:#5eead4;color:#0b1120;border:1px solid rgba(255,255,255,.2);box-shadow:0 4px 14px rgba(20,184,166,.35)}
.sh-end .btn:hover{background:#2dd4bf;color:#0b1120}
.sh-menu{display:none}
.sh-panel{display:none;background:var(--bg);color:var(--ink);border-top:1px solid #1e293b;padding-block:8px 16px}
.sh-panel details{border-bottom:1px solid var(--line)}
.sh-panel summary{display:flex;align-items:center;justify-content:space-between;min-height:52px;font-weight:700;cursor:pointer;list-style:none}
.sh-panel summary::-webkit-details-marker{display:none}
.sh-panel summary::after{content:"+";font-size:20px;color:var(--muted)}
.sh-panel details[open] summary::after{content:"−"}
.sh-panel a,.sh-panel .sh-ctl{display:flex;align-items:center;min-height:48px;padding:6px 4px;color:var(--ink);text-decoration:none;font-size:16px;width:100%;justify-content:flex-start}
.sh-panel nav>a:not(.btn){font-weight:700;border-bottom:1px solid var(--line)}
.sh-panel a[aria-current=page]{color:var(--acc);font-weight:700}
.sh-panel .btn{margin-top:12px;width:100%;justify-content:center;color:var(--btn-ink)}
.sh.open .sh-panel{display:block}
.ico{width:18px;height:18px;display:inline-block}
@media (max-width:1279px){.sh-nav,.sh-end .hide-sm{display:none}.sh-menu{display:inline-flex}}
/* page hero band (inner pages) */
.ph{background:radial-gradient(800px 320px at 85% 0%,rgba(20,184,166,.20),transparent 70%),#0b1120;color:#f8fafc;padding-block:28px 40px;border-bottom:1px solid #1e293b}
.ph a{color:#5eead4}
.ph h1{color:#fff;max-width:900px;margin:0 0 12px}
.ph p,.ph .muted{color:#cbd5e1!important;font-size:17px;max-width:820px;margin:0}
.ph .updated{color:#94a3b8!important;font-size:14px;margin-top:8px}
.crumbs{font-size:14px;margin:0 0 14px;color:#94a3b8}
.crumbs ol{list-style:none;display:flex;flex-wrap:wrap;gap:6px;margin:0;padding:0}
.crumbs li+li::before{content:"/";margin-inline-end:6px;color:#475569}
.crumbs a{color:#cbd5e1;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;min-height:44px;min-width:44px;padding-inline:2px}
.crumbs a:hover{color:#fff;text-decoration:underline}
.ph-status{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#5eead4;margin-bottom:10px}
.ph-status::before{content:"";width:8px;height:8px;border-radius:99px;background:#5eead4}
/* related + closing band */
.rel{margin-top:48px}
.rel h2{font-size:20px;margin:0 0 14px}
.rel ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:12px}
.rel a{display:flex;flex-direction:column;gap:4px;height:100%;padding:16px 18px;border:1px solid var(--line);border-radius:14px;background:var(--card);color:var(--ink);text-decoration:none;min-height:44px;transition:border-color .15s}
.rel a:hover{border-color:var(--acc)}
.rel a strong{color:var(--acc)}
.rel a span{font-size:14px;color:var(--muted)}
.cb{margin-top:48px;border-radius:20px;padding:32px 24px;background:radial-gradient(600px 240px at 90% 0%,rgba(20,184,166,.25),transparent 70%),#0b1120;color:#f8fafc;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px}
.cb h2{color:#fff;margin:0 0 6px;font-size:clamp(20px,3vw,26px)}
.cb p{margin:0;color:#cbd5e1;max-width:620px}
.cb .btn{background:#5eead4;color:#0b1120}
/* footer */
.sf{background:#0b1120;color:#94a3b8;margin-top:56px;padding-block:40px 24px;font-size:14px}
.sf-top{display:grid;gap:24px;grid-template-columns:1fr}
@media (min-width:768px){.sf-top{grid-template-columns:1.4fr repeat(4,1fr)}}
.sf .sh-logo{font-size:18px}
.sf h2{font-size:14px;color:#f8fafc;margin:0 0 8px}
.sf ul{list-style:none;margin:0;padding:0}
.sf a{display:inline-flex;align-items:center;min-height:44px;min-width:44px;color:#cbd5e1;text-decoration:none}
.sf a:hover{color:#fff;text-decoration:underline}
.sf p{margin:8px 0 0}
.sf-bottom{border-top:1px solid #1e293b;margin-top:24px;padding-top:12px;display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px}
/* sticky mobile CTA */
.stk{position:fixed;inset-inline:0;bottom:0;z-index:45;background:var(--bg);border-top:1px solid var(--line);padding:8px 16px;display:none}
.stk .btn{width:100%}
@media (max-width:767px){.stk.on{display:block}}
input[type=file]{width:100%;min-height:48px;padding:10px 12px;border-radius:10px;border:1px dashed var(--field);background:var(--bg);color:var(--ink);font:inherit}
input[type=file]::file-selector-button{min-height:36px;margin-inline-end:10px;padding:6px 12px;border-radius:8px;border:1px solid var(--field);background:var(--card);color:var(--ink);font:inherit;cursor:pointer}
/* cookie notice */
.ck-btns{display:flex;gap:8px;flex-wrap:nowrap}@media (max-width:480px){.ck.ck-ga .wrap{flex-wrap:wrap;row-gap:4px}.ck.ck-ga p{flex:1 1 100%;margin:0}.ck.ck-ga .ck-btns{flex:1 1 100%}.ck.ck-ga .ck-btns .btn{flex:1}}.ck-btns .btn{min-height:44px;padding:8px 14px;white-space:nowrap}.ck p a{display:inline-block;padding:12px 4px;margin:-12px 0}.ck .ck-alt{background:transparent;border:1px solid currentColor;color:inherit}
.ck{position:fixed;inset-inline:0;bottom:0;z-index:50;background:var(--surface);border-top:1px solid var(--line);font-size:14px}
.ck .wrap{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding-block:10px}
.ck p{margin:0;flex:1;min-width:220px}
.ck-short{display:none}
@media (max-width:480px){.ck{font-size:13px;line-height:1.5}.ck .wrap{flex-wrap:nowrap;gap:8px;padding-block:6px}.ck p{min-width:0}.ck-long{display:none}.ck-short{display:inline}.ck .btn{flex:none;min-height:44px;padding:6px 14px}}
/* forms */
label,.lbl{display:block;font-weight:600;margin:16px 0 6px}
input[type=text],input[type=email],input[type=number],input[type=url],input[type=tel],input[type=search],select,textarea{width:100%;min-height:48px;padding:10px 12px;border-radius:10px;border:1px solid var(--field);background:var(--bg);color:var(--ink);font:inherit}
.check{display:flex;align-items:center;gap:10px;min-height:44px;margin:0;font-weight:400;cursor:pointer}
.check input{width:20px;height:20px;flex:none;accent-color:var(--acc)}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
</style>
@stack('styles')
</head>
<body>
<a class="skip" href="#main">{{ $ar ? 'انتقل إلى المحتوى' : 'Skip to content' }}</a>
<header class="sh" id="sh">
    <div class="wrap">
        <div class="sh-in">
            <a class="sh-logo" href="{{ $u('/') }}" aria-label="AdsRanger" translate="no"><img src="/brand/adsranger-mark-teal.svg" alt="" width="32" height="32"><span aria-hidden="true">Ads<b>Ranger</b></span></a>
            <nav class="sh-nav" aria-label="{{ $ar ? 'الرئيسية' : 'Main' }}">
                @foreach ($menu as $i => $g)
                    @if (isset($g['path']))
                        <a href="{{ $u($g['path']) }}" @if ($arPath === $g['path']) aria-current="page" @endif>{!! $bd($g['label']) !!}</a>
                    @else
                        <div class="mm {{ in_array($arPath, $g['items'], true) ? 'cur' : '' }}">
                            <button type="button" aria-expanded="false" aria-controls="mm-{{ $i }}">{!! $bd($g['label']) !!}<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 4l4 4 4-4"/></svg></button>
                            <div class="mm-panel" id="mm-{{ $i }}">
                                @foreach ($g['items'] as $p)
                                    <a href="{{ $u($p) }}" @if ($arPath === $p) aria-current="page" @endif><strong>{!! $bd($site::name($p, $ar)) !!}</strong><span>{!! $bd($site::line($p, $ar)) !!}</span></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>
            <div class="sh-end">
                <a class="sh-ctl" href="{{ $switchUrl }}" lang="{{ $ar ? 'en' : 'ar' }}" hreflang="{{ $ar ? 'en' : 'ar' }}">{{ $ar ? 'English' : 'العربية' }}</a>
                <button type="button" class="sh-ctl hide-sm" data-theme-toggle aria-pressed="false" aria-label="{{ $ar ? 'الوضع الداكن' : 'Dark mode' }}" title="{{ $ar ? 'الوضع الداكن' : 'Dark mode' }}"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
                <a class="sh-ctl hide-sm" href="/app/login">{{ $ar ? 'تسجيل الدخول' : 'Sign in' }}</a>
                <a class="btn hide-sm" href="{{ $u('/waitlist') }}" data-cta="waitlist" data-pos="header">{{ $waitlistLabel }}</a>
                <button type="button" class="sh-ctl sh-menu" id="sh-menu" aria-expanded="false" aria-controls="sh-panel"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>&nbsp;{{ $ar ? 'القائمة' : 'Menu' }}</button>
            </div>
        </div>
    </div>
    <div class="sh-panel" id="sh-panel">
        <div class="wrap">
            <nav aria-label="{{ $ar ? 'القائمة' : 'Menu' }}">
                @foreach ($menu as $g)
                    @if (isset($g['path']))
                        <a href="{{ $u($g['path']) }}" @if ($arPath === $g['path']) aria-current="page" @endif>{!! $bd($g['label']) !!}</a>
                    @else
                        <details @if (in_array($arPath, $g['items'], true)) open @endif>
                            <summary>{!! $bd($g['label']) !!}</summary>
                            @foreach ($g['items'] as $p)
                                <a href="{{ $u($p) }}" @if ($arPath === $p) aria-current="page" @endif>{!! $bd($site::name($p, $ar)) !!}</a>
                            @endforeach
                        </details>
                    @endif
                @endforeach
                <a href="/app/login">{{ $ar ? 'تسجيل الدخول' : 'Sign in' }}</a>
                <button type="button" class="sh-ctl" data-theme-toggle aria-pressed="false">{{ $ar ? 'الوضع الداكن' : 'Dark mode' }}</button>
                <a class="btn" href="{{ $u('/waitlist') }}" data-cta="waitlist" data-pos="menu">{{ $waitlistLabel }}</a>
            </nav>
        </div>
    </div>
</header>
<main id="main" tabindex="-1">
    @hasSection('hero')
        <div class="ph">
            <div class="wrap">
                <nav class="crumbs" aria-label="{{ $ar ? 'مسار الصفحة' : 'Breadcrumb' }}"><ol>
                    <li><a href="{{ $u('/') }}">{!! $bd($site::name('/', $ar)) !!}</a></li>
                    <li aria-current="page">{{ $crumb }}</li>
                </ol></nav>
                @yield('hero')
            </div>
        </div>
    @endif
    @yield('top')
    <div class="wrap">
        @yield('content')
        @if ($related)
            <section class="rel" aria-labelledby="rel-h">
                <h2 id="rel-h">{{ $ar ? 'صفحات ذات صلة' : 'Related pages' }}</h2>
                <ul>
                    @foreach ($related as $p)
                        <li><a href="{{ $u($p) }}"><strong>{!! $bd($site::name($p, $ar)) !!}</strong><span>{!! $bd($site::line($p, $ar)) !!}</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if ($closing)
            <section class="cb" aria-labelledby="cb-h">
                <div>
                    <h2 id="cb-h">{{ $ar ? 'كن أول من يعرف عند الإطلاق' : 'Be the first to know at launch' }}</h2>
                    <p>{{ $ar ? 'اترك بريدك واسم وكالتك ودولتك، وسنراسلك عند الإطلاق في 2027.' : 'Leave your email, agency name and country, and we\'ll email you at launch in 2027.' }}</p>
                </div>
                <a class="btn" href="{{ $u('/waitlist') }}?type=agency" data-cta="waitlist" data-pos="closing">{{ $waitlistLabel }}</a>
            </section>
        @endif
    </div>
</main>
<footer class="sf">
    <div class="wrap">
        <div class="sf-top">
            <div>
                <a class="sh-logo" href="{{ $u('/') }}" aria-label="AdsRanger" translate="no"><img src="/brand/adsranger-mark-teal.svg" alt="" width="28" height="28"><span aria-hidden="true">Ads<b>Ranger</b></span></a>
                <p>{!! $ar ? 'منصة عربية أولًا لإعلانات الوكالات وظهور عملائها في البحث. ما زالت <bdi dir="ltr">AdsRanger</bdi> قيد التطوير، والإطلاق في 2027.' : 'An Arabic-first platform for agencies\' ads and their clients\' search visibility. AdsRanger is in development and launching in 2027.' !!}</p>
            </div>
            @foreach ($site::footer($ar) as $ci => [$heading, $paths])
                <nav aria-labelledby="sf-h{{ $ci }}">
                    <h2 id="sf-h{{ $ci }}">{!! $bd($heading) !!}</h2>
                    <ul>
                        @foreach ($paths as $p)
                            <li><a href="{{ $u($p) }}">{!! $bd($site::name($p, $ar)) !!}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>
        <div class="sf-bottom">
            <span>© 2026 <bdi dir="ltr">AdsRanger</bdi> · {{ $ar ? 'عمّان، الأردن' : 'Amman, Jordan' }}</span>
            <a href="mailto:support@adsranger.com"><bdi dir="ltr">support@adsranger.com</bdi></a>
        </div>
    </div>
</footer>
@if ($sticky)
<div class="stk" id="ar-sticky"><a class="btn" href="{{ $u('/waitlist') }}?type=agency" data-cta="waitlist" data-pos="sticky">{{ $waitlistLabel }}</a></div>
@endif
@php($gaId = config('services.ga4.measurement_id'))
<div class="ck{{ $gaId ? ' ck-ga' : '' }}" id="ar-cookie-banner" role="region" aria-label="{{ $ar ? 'إشعار ملفات تعريف الارتباط' : 'Cookie notice' }}" hidden>
    <div class="wrap">
@if ($gaId)
        <p><span class="ck-long">{!! $ar ? 'نستخدم ملفات أساسية فقط، ولا نشغّل <bdi dir="ltr">Google Analytics</bdi> إلا بموافقتك، ودون أي ميزات إعلانية. <a href="/privacy">سياسة الخصوصية</a>' : 'We use essential cookies only, and run Google Analytics only with your OK, with no ad features. <a href="/en/privacy">Privacy policy</a>' !!}</span><span class="ck-short">{!! $ar ? 'ملفات أساسية، والتحليلات بموافقتك فقط. <a href="/privacy">الخصوصية</a>' : 'Essential cookies; analytics only if you agree. <a href="/en/privacy">Privacy</a>' !!}</span></p>
        <div class="ck-btns"><button type="button" class="btn" id="ar-cookie-accept" data-consent="analytics">{{ $ar ? 'قبول التحليلات' : 'Accept analytics' }}</button><button type="button" class="btn ck-alt" data-consent="essential">{{ $ar ? 'الأساسية فقط' : 'Essential only' }}</button></div>
@else
        <p><span class="ck-long">{!! $ar ? 'نستخدم ملفات تعريف الارتباط الأساسية فقط لتشغيل الجلسات وحفظ اللغة والأمان، دون أي أدوات تتبّع إعلانية. راجع <a href="/privacy">سياسة الخصوصية</a>.' : 'We only use essential cookies for sign-in sessions, language and security, with no third-party ad trackers. See our <a href="/en/privacy">privacy policy</a>.' !!}</span><span class="ck-short">{!! $ar ? 'ملفات تعريف ارتباط أساسية فقط، دون تتبّع إعلاني. <a href="/privacy">الخصوصية</a>' : 'Essential cookies only, no ad trackers. <a href="/en/privacy">Privacy</a>' !!}</span></p>
        <button type="button" class="btn" id="ar-cookie-accept" data-consent="essential">{{ $ar ? 'حسنًا' : 'Got it' }}</button>
@endif
    </div>
</div>
<script>
(function(){
  var h=document.getElementById('sh'),m=document.getElementById('sh-menu');
  if(m){m.addEventListener('click',function(){var o=h.classList.toggle('open');m.setAttribute('aria-expanded',o?'true':'false');});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&h.classList.contains('open')){h.classList.remove('open');m.setAttribute('aria-expanded','false');m.focus();}});}
  var groups=document.querySelectorAll('.mm>button');
  function closeAll(except){groups.forEach(function(b){if(b!==except){b.setAttribute('aria-expanded','false');}});}
  groups.forEach(function(b){b.addEventListener('click',function(e){e.stopPropagation();var o=b.getAttribute('aria-expanded')==='true';closeAll(b);b.setAttribute('aria-expanded',o?'false':'true');});});
  document.addEventListener('click',function(e){if(!e.target.closest('.mm')){closeAll();}});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'){var open=document.querySelector('.mm>button[aria-expanded=true]');if(open){open.setAttribute('aria-expanded','false');open.focus();}}});
  var root=document.documentElement;
  function isDark(){var t=root.getAttribute('data-theme');return t?t==='dark':window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;}
  function sync(){var d=isDark();document.querySelectorAll('[data-theme-toggle]').forEach(function(b){b.setAttribute('aria-pressed',d?'true':'false');});}
  document.querySelectorAll('[data-theme-toggle]').forEach(function(b){b.addEventListener('click',function(){var d=!isDark();root.setAttribute('data-theme',d?'dark':'light');try{localStorage.setItem('ar_theme',d?'dark':'light');}catch(e){}sync();});});
  sync();
  var ck=document.getElementById('ar-cookie-banner');
  // Consent (GA4, 2026-11-12): ar_consent=analytics|essential. GA loads only after "analytics"; no ad features.
  var GA=@json($gaId);
  function consent(){var m=document.cookie.match(/(?:^|; )ar_consent=(analytics|essential)/);return m?m[1]:null;}
  function loadGa(){if(!GA||window.gtag){return;}window.dataLayer=window.dataLayer||[];window.gtag=function(){window.dataLayer.push(arguments);};
    window.gtag('js',new Date());window.gtag('config',GA,{allow_google_signals:false,allow_ad_personalization_signals:false});
    var s=document.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(GA);document.head.appendChild(s);}
  if(consent()==='analytics'){loadGa();}
  function showCk(){if(ck){ck.hidden=false;document.body.style.paddingBottom=ck.offsetHeight+'px';}}
  if(ck&&!consent()){showCk();}
  document.querySelectorAll('[data-cookie-settings]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();showCk();});});
  var ok=document.getElementById('ar-cookie-accept');
  var stk=document.getElementById('ar-sticky'),cb=document.querySelector('.cb,.hp-close'),hero=document.querySelector('.ph,.hp-hero');
  function stkSync(){if(!stk){return;}var cookieShown=ck&&!ck.hidden,past=hero?hero.getBoundingClientRect().bottom<0:true,atEnd=cb?cb.getBoundingClientRect().top<window.innerHeight:false;var on=!cookieShown&&past&&!atEnd;stk.classList.toggle('on',on);document.body.style.paddingBottom=cookieShown?ck.offsetHeight+'px':(on||past?'64px':'');}
  if(stk&&window.matchMedia('(max-width:767px)').matches){window.addEventListener('scroll',stkSync,{passive:true});stkSync();}
  document.querySelectorAll('#ar-cookie-banner [data-consent]').forEach(function(b){b.addEventListener('click',function(){var v=b.getAttribute('data-consent');
    document.cookie='ar_consent='+v+'; Max-Age=15552000; Path=/; SameSite=Lax; Secure';ck.hidden=true;document.body.style.paddingBottom='';stkSync();if(v==='analytics'){loadGa();}});});
  document.addEventListener('click',function(e){var a=e.target.closest('[data-cta]');if(a&&window.gtag){window.gtag('event','cta_click',{cta:a.getAttribute('data-cta'),position:a.getAttribute('data-pos')});}});
})();
</script>
@stack('scripts')
</body>
</html>
