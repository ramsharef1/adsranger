@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$navItems = $ar ? [
    ['/', 'الرئيسية'],
    ['/pricing', 'الأسعار'],
    ['/integrations', 'المنصات الإعلانية'],
    ['/demo-report', 'تقرير تجريبي'],
    ['/mena-calendar', 'تقويم المواسم'],
    ['/ramadan-2027', 'رمضان 2027'],
    ['/waitlist', 'قائمة الانتظار'],
] : [
    ['/en', 'Home'],
    ['/en/pricing', 'Pricing'],
    ['/en/integrations', 'Ad networks'],
    ['/en/demo-report', 'Sample report'],
    ['/en/mena-calendar', 'Seasons calendar'],
    ['/en/ramadan-2027', 'Ramadan 2027'],
    ['/en/waitlist', 'Waitlist'],
];
$here = '/'.ltrim(request()->path(), '/');
$here = $here === '/' ? '/' : rtrim($here, '/');
@endphp
{{-- Shared public header (R-17, UX-103): one look on every public and legal page, whatever the page's own CSS or width. --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap">
<style>
html{overflow-x:clip}
.arn{box-sizing:border-box;width:100vw;margin-inline:calc(50% - 50vw);margin-bottom:12px;border-bottom:1px solid #232c4d;background:transparent;font-family:{!! $ar ? '"IBM Plex Sans Arabic",' : '' !!}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;line-height:1.4}
.arn *{box-sizing:border-box}
.arn .arn-in{max-width:1120px;margin:0 auto;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.arn .arn-logo{display:inline-flex;align-items:center;gap:10px;min-height:44px;direction:ltr;color:#eef1fb;text-decoration:none;font-weight:700;font-size:18px;letter-spacing:.2px}
.arn .arn-logo img{width:34px;height:34px;display:block}
.arn .arn-logo b{color:#5EEAD4;font-weight:700}
.arn nav.arn-links{display:flex;align-items:center;gap:4px;flex-wrap:wrap;padding:0;margin:0;border:0;justify-content:flex-end}
.arn .arn-links a{display:inline-flex;align-items:center;min-height:44px;padding:6px 10px;border-radius:8px;color:#9aa3c0;text-decoration:none;font-size:14px;font-weight:500}
.arn .arn-links a:hover,.arn .arn-links a:focus-visible{color:#eef1fb;background:rgba(148,163,184,.12)}
.arn .arn-links a[aria-current="page"]{color:#eef1fb;font-weight:700}
.arn .arn-links a.arn-lang{color:#5EEAD4;font-weight:600}
.arn .arn-links a.arn-signin{border:1px solid #232c4d;color:#eef1fb;font-weight:600;padding:6px 14px}
.arn .arn-toggle{display:none;min-height:44px;min-width:44px;padding:6px 14px;border-radius:8px;border:1px solid #232c4d;background:transparent;color:#eef1fb;font:inherit;font-size:14px;font-weight:600;cursor:pointer;align-items:center;gap:8px}
.arn .arn-toggle span{display:inline-block;width:16px;height:2px;background:currentColor;box-shadow:0 5px 0 currentColor,0 -5px 0 currentColor}
@media (max-width:899px){
  .arn.js .arn-toggle{display:inline-flex}
  .arn.js nav.arn-links{display:none;width:100%;flex-direction:column;align-items:stretch;gap:2px;padding:6px 0 4px}
  .arn.js.open nav.arn-links{display:flex}
  .arn.js .arn-links a{width:100%}
}
</style>
<div class="arn" id="arn" role="banner">
    <div class="arn-in">
        <a class="arn-logo" href="{{ $base ?: '/' }}" aria-label="AdsRanger" translate="no">
            <img src="/brand/adsranger-mark-teal.svg" alt="" width="34" height="34"><span aria-hidden="true">Ads<b>Ranger</b></span>
        </a>
        <button type="button" class="arn-toggle" aria-expanded="false" aria-controls="arn-links"><span aria-hidden="true"></span>{{ $ar ? 'القائمة' : 'Menu' }}</button>
        <nav class="arn-links" id="arn-links" aria-label="{{ $ar ? 'القائمة الرئيسية' : 'Main menu' }}">
            @foreach ($navItems as [$href, $label])
                <a href="{{ $href }}" @if ($here === $href) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a class="arn-lang" href="{{ $switch_url ?? ($ar ? '/en' : '/') }}" lang="{{ $ar ? 'en' : 'ar' }}" hreflang="{{ $ar ? 'en' : 'ar' }}">{{ $ar ? 'English' : 'العربية' }}</a>
            <a class="arn-signin" href="/app/login">{{ $ar ? 'تسجيل الدخول' : 'Sign in' }}</a>
        </nav>
    </div>
</div>
<script>
(function(){var h=document.getElementById('arn');if(!h){return;}h.classList.add('js');var b=h.querySelector('.arn-toggle');b.addEventListener('click',function(){var o=h.classList.toggle('open');b.setAttribute('aria-expanded',o?'true':'false');});})();
</script>
