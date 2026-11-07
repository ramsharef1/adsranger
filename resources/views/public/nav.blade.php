@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$navItems = $ar ? [
    ['/', 'الرئيسية'],
    ['/pricing', 'الأسعار'],
    ['/integrations', 'المنصات الإعلانية'],
    ['/demo-report', 'تقرير تجريبي'],
    ['/mena-calendar', 'مواسم الشرق الأوسط'],
    ['/ramadan-2027', 'رمضان 2027'],
    ['/waitlist', 'قائمة الانتظار'],
] : [
    ['/en', 'Home'],
    ['/en/pricing', 'Pricing'],
    ['/en/integrations', 'Ad networks'],
    ['/en/demo-report', 'Live Demo'],
    ['/en/mena-calendar', 'MENA Calendar'],
    ['/en/ramadan-2027', 'Ramadan 2027'],
    ['/en/waitlist', 'Waitlist'],
];
@endphp
<nav style="display:flex;justify-content:space-between;align-items:center;padding:20px 0;gap:12px;flex-wrap:wrap;border-bottom:1px solid var(--line);margin-bottom:12px">
    <a class="logo" href="{{ $base ?: '/' }}" style="font-weight:700;letter-spacing:.2px;direction:ltr;color:var(--ink);text-decoration:none;display:flex;align-items:center;gap:10px">
        <img src="/brand/adsranger-mark-teal.svg" alt="" width="34" height="34"><span>Ads<b style="color:var(--acc)">Ranger</b></span>
    </a>
    <div class="nr" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        @foreach ($navItems as [$href, $label])
            <a class="lang" href="{{ $href }}" style="color:var(--muted);text-decoration:none;font-size:13.5px;padding:6px 9px">{{ $label }}</a>
        @endforeach
        <a class="lang" href="{{ $switch_url ?? ($ar ? '/en' : '/') }}" lang="{{ $ar ? 'en' : 'ar' }}" style="color:var(--acc);text-decoration:none;font-size:13.5px;padding:6px 9px;font-weight:600">{{ $ar ? 'English' : 'العربية' }}</a>
        <a class="btn o" href="/app/login" style="display:inline-block;padding:8px 15px;border-radius:10px;background:transparent;border:1px solid var(--line);color:var(--ink);text-decoration:none;font-weight:600;font-size:13.5px">{{ $ar ? 'تسجيل الدخول' : 'Sign in' }}</a>
    </div>
</nav>
