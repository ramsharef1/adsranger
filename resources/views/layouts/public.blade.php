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
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $arUrl = \App\Support\PublicUrl::absolute($arPath, 'ar');
    $enUrl = \App\Support\PublicUrl::absolute($arPath, 'en');
    $switchUrl = \App\Support\PublicUrl::path($arPath, $ar ? 'en' : 'ar');
    $nav = [
        ['/', $ar ? 'الرئيسية' : 'Home'],
        ['/ramadan-2027', $ar ? 'رمضان 2027' : 'Ramadan 2027'],
        ['/mena-calendar', $ar ? 'تقويم المواسم' : 'Seasons calendar'],
        ['/integrations', $ar ? 'المنصات الإعلانية' : 'Ad platforms'],
        ['/pricing', $ar ? 'الباقات' : 'Plans'],
    ];
    $footerLinks = array_merge($nav, [
        ['/demo-report', $ar ? 'تقرير تجريبي' : 'Sample report'],
        ['/security', $ar ? 'الأمان' : 'Security'],
        ['/solutions/business-owners', $ar ? 'لأصحاب المشاريع' : 'For business owners'],
        ['/partners', $ar ? 'الشركاء' : 'Partners'],
        ['/about', $ar ? 'عن AdsRanger' : 'About'],
        ['/contact', $ar ? 'تواصل معنا' : 'Contact'],
        ['/privacy', $ar ? 'الخصوصية' : 'Privacy'],
        ['/terms', $ar ? 'الشروط' : 'Terms'],
        ['/data-deletion', $ar ? 'حذف البيانات' : 'Data deletion'],
    ]);
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
<link rel="canonical" href="{{ $selfUrl }}">
<link rel="alternate" hreflang="ar" href="{{ $arUrl }}">
<link rel="alternate" hreflang="en" href="{{ $enUrl }}">
<link rel="alternate" hreflang="x-default" href="{{ $arUrl }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="AdsRanger">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $meta }}">
<meta property="og:url" content="{{ $selfUrl }}">
<meta property="og:image" content="https://adsranger.com/brand/icon-512.png">
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
main{display:block;padding-block:8px 48px}
main p a,main li a,main td a{text-decoration:underline}
h1{font-size:clamp(28px,4.5vw,42px);line-height:1.25;margin:0 0 12px}
h2{font-size:clamp(21px,3vw,26px);line-height:1.35;margin:32px 0 12px}
h3{font-size:18px;margin:0 0 6px}
/* header — #0f172a dark command bar */
.sh{border-bottom:1px solid #1e293b;background:#0f172a;color:#f8fafc}
.sh-in{display:flex;align-items:center;gap:8px;min-height:66px}
.sh-logo{display:inline-flex;align-items:center;gap:8px;min-height:44px;min-width:44px;direction:ltr;color:#ffffff;text-decoration:none;font-weight:700;font-size:19px}
.sh-logo img{width:32px;height:32px}
.sh-logo b{color:#2dd4bf}
.sh-nav{display:flex;align-items:center;gap:2px;margin-inline-start:16px}
.sh-nav a,.sh-ctl{display:inline-flex;align-items:center;justify-content:center;min-height:44px;min-width:44px;padding:6px 11px;border-radius:8px;color:#cbd5e1;text-decoration:none;font-size:14.5px;font-weight:500;background:transparent;border:0;font-family:inherit;cursor:pointer}
.sh-nav a[aria-current=page]{font-weight:700;color:#ffffff;background:rgba(255,255,255,.08)}
.sh-nav a:hover,.sh-ctl:hover{color:#ffffff;background:rgba(255,255,255,.08)}
.sh-end{display:flex;align-items:center;gap:6px;margin-inline-start:auto}
.sh-end .btn{padding:8px 16px;border-radius:8px;font-weight:700}
.sh-end .btn:hover{background:#f0531c;color:#ffffff;opacity:1}
.sh-menu{display:none}
.sh-panel{display:none;border-top:1px solid var(--line);padding-block:8px 16px}
.sh-panel a,.sh-panel .sh-ctl{display:flex;align-items:center;min-height:48px;padding:8px 4px;color:var(--ink);text-decoration:none;font-size:16px;width:100%;justify-content:flex-start}
.sh-panel a[aria-current=page]{font-weight:700;color:var(--acc)}
.sh-panel .btn{margin-top:8px;width:100%;justify-content:center;color:var(--btn-ink)}
.sh.open .sh-panel{display:block}
.ico{width:18px;height:18px;display:inline-block}
@media (max-width:1199px){
  .sh-nav,.sh-end .hide-sm{display:none}
  .sh-menu{display:inline-flex}
}
/* footer */
.sf{border-top:1px solid var(--line);margin-top:32px;padding-block:16px 24px;color:var(--muted);font-size:14px}
.sf ul{list-style:none;display:flex;flex-wrap:wrap;gap:0 4px;margin:0;padding:0}
.sf a{display:inline-flex;align-items:center;min-height:44px;min-width:44px;padding-inline:6px;color:var(--muted);text-decoration:none}
.sf a:hover{color:var(--ink);text-decoration:underline}
.sf p{margin:8px 0 0}
/* cookie notice */
.ck{position:fixed;inset-inline:0;bottom:0;z-index:50;background:var(--surface);border-top:1px solid var(--line);font-size:14px}
.ck .wrap{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding-block:10px}
.ck p{margin:0;flex:1;min-width:220px}
/* forms */
label,.lbl{display:block;font-weight:600;margin:16px 0 6px}
input[type=text],input[type=email],input[type=number],select{width:100%;min-height:48px;padding:10px 12px;border-radius:10px;border:1px solid var(--field);background:var(--bg);color:var(--ink);font:inherit}
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
                @foreach ($nav as [$p, $label])
                    <a href="{{ $u($p) }}" @if ($arPath === $p) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="sh-end">
                <a class="sh-ctl" href="{{ $switchUrl }}" lang="{{ $ar ? 'en' : 'ar' }}" hreflang="{{ $ar ? 'en' : 'ar' }}">{{ $ar ? 'English' : 'العربية' }}</a>
                <button type="button" class="sh-ctl hide-sm" data-theme-toggle aria-pressed="false" aria-label="{{ $ar ? 'الوضع الداكن' : 'Dark mode' }}" title="{{ $ar ? 'الوضع الداكن' : 'Dark mode' }}"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
                <a class="sh-ctl hide-sm" href="/app/login">{{ $ar ? 'تسجيل الدخول' : 'Sign in' }}</a>
                <a class="btn hide-sm" href="{{ $u('/waitlist') }}">{{ $waitlistLabel }}</a>
                <button type="button" class="sh-ctl sh-menu" id="sh-menu" aria-expanded="false" aria-controls="sh-panel"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>&nbsp;{{ $ar ? 'القائمة' : 'Menu' }}</button>
            </div>
        </div>
        <div class="sh-panel" id="sh-panel">
            <nav aria-label="{{ $ar ? 'القائمة' : 'Menu' }}">
                @foreach ($nav as [$p, $label])
                    <a href="{{ $u($p) }}" @if ($arPath === $p) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a href="/app/login">{{ $ar ? 'تسجيل الدخول' : 'Sign in' }}</a>
                <button type="button" class="sh-ctl" data-theme-toggle aria-pressed="false">{{ $ar ? 'الوضع الداكن' : 'Dark mode' }}</button>
                <a class="btn" href="{{ $u('/waitlist') }}">{{ $waitlistLabel }}</a>
            </nav>
        </div>
    </div>
</header>
<main id="main" tabindex="-1">
    <div class="wrap">
        @yield('content')
    </div>
</main>
<footer class="sf">
    <div class="wrap">
        <nav aria-label="{{ $ar ? 'روابط الموقع' : 'Site links' }}">
            <ul>
                @foreach ($footerLinks as [$p, $label])
                    <li><a href="{{ $u($p) }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        <p>{!! $ar ? '<bdi dir="ltr">AdsRanger</bdi> قيد التطوير، والإطلاق في 2027.' : 'AdsRanger is in development and launching in 2027.' !!}</p>
        <p>© 2026 <bdi dir="ltr">AdsRanger</bdi> · {{ $ar ? 'عمّان' : 'Amman' }}</p>
    </div>
</footer>
<div class="ck" id="ar-cookie-banner" role="region" aria-label="{{ $ar ? 'إشعار ملفات تعريف الارتباط' : 'Cookie notice' }}" hidden>
    <div class="wrap">
        <p>{!! $ar ? 'نستخدم ملفات تعريف الارتباط الأساسية فقط لتشغيل الجلسات وحفظ اللغة والأمان، دون أي أدوات تتبّع إعلانية. راجع <a href="/privacy">سياسة الخصوصية</a>.' : 'We only use essential cookies for sign-in sessions, language and security, with no third-party ad trackers. See our <a href="/en/privacy">privacy policy</a>.' !!}</p>
        <button type="button" class="btn" id="ar-cookie-accept">{{ $ar ? 'حسنًا' : 'Got it' }}</button>
    </div>
</div>
<script>
(function(){
  var h=document.getElementById('sh'),m=document.getElementById('sh-menu');
  if(m){m.addEventListener('click',function(){var o=h.classList.toggle('open');m.setAttribute('aria-expanded',o?'true':'false');});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&h.classList.contains('open')){h.classList.remove('open');m.setAttribute('aria-expanded','false');m.focus();}});}
  var root=document.documentElement;
  function isDark(){var t=root.getAttribute('data-theme');return t?t==='dark':window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;}
  function sync(){var d=isDark();document.querySelectorAll('[data-theme-toggle]').forEach(function(b){b.setAttribute('aria-pressed',d?'true':'false');});}
  document.querySelectorAll('[data-theme-toggle]').forEach(function(b){b.addEventListener('click',function(){var d=!isDark();root.setAttribute('data-theme',d?'dark':'light');try{localStorage.setItem('ar_theme',d?'dark':'light');}catch(e){}sync();});});
  sync();
  var ck=document.getElementById('ar-cookie-banner');
  if(ck&&document.cookie.indexOf('ar_cookie_ok=1')===-1){ck.hidden=false;document.body.style.paddingBottom=ck.offsetHeight+'px';}
  var ok=document.getElementById('ar-cookie-accept');
  if(ok){ok.addEventListener('click',function(){document.cookie='ar_cookie_ok=1; Max-Age=15552000; Path=/; SameSite=Lax';ck.hidden=true;document.body.style.paddingBottom='';});}
})();
</script>
@stack('scripts')
</body>
</html>
