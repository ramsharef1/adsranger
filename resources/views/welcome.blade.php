@php
$ar = ($lang ?? 'ar') === 'ar';
$t = $ar ? [
  'title' => 'AdsRanger · منصة تقارير الإعلانات للوكالات',
  'signin' => 'تسجيل الدخول',
  'switch' => 'English', 'switch_url' => '/en',
  'tag' => 'نسخة تجريبية خاصة · لوكالات الشرق الأوسط',
  'h1' => 'كل حملة تُقاس بالنتيجة التي صُممت من أجلها.',
  'lead' => 'منصة تقارير ميتا وجوجل للوكالات الإعلانية: الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات تُحسب حسب الهدف الحقيقي لكل حملة، مع تنبيهات يومية تكشف هدر الميزانية، وتقارير بالعربية والإنجليزية يفهمها عملاؤك.',
  'cta' => 'افتح المنصة',
  'f' => [
    ['ربط ميتا وجوجل', 'اربط حسابات وكالتك مرة واحدة، وتتحدث البيانات كل صباح.'],
    ['نتائج حسب الهدف', 'النتيجة تتبع هدف المجموعة الإعلانية: رسائل، مكالمات، عملاء، مشتريات.'],
    ['مكتب يومي وتنبيهات', 'تتبّع معطّل، إنفاق بلا نتائج، ضعف في التسليم، وارتفاع في التكلفة.'],
    ['مؤشر صحة وأهداف', 'تقييم كل عميل من 0 إلى 100 مقابل الميزانية وتكلفة النتيجة والأهداف.'],
    ['روابط وتقارير للعملاء', 'روابط آمنة للقراءة فقط وتقرير شهري بالعربية أو الإنجليزية.'],
  ],
  'foot' => 'عمّان', 'env' => 'بيئة تجريبية',
] : [
  'title' => 'AdsRanger · Results-first ads reporting for agencies',
  'signin' => 'Sign in',
  'switch' => 'العربية', 'switch_url' => '/',
  'tag' => 'Private beta · MENA agencies',
  'h1' => 'Every campaign judged by the result it was built for.',
  'lead' => "Meta and Google Ads reporting for agencies: messages, calls, leads, bookings and sales counted by each campaign's real goal, with daily alerts that catch wasted spend and Arabic/English reports your clients understand.",
  'cta' => 'Open the platform',
  'f' => [
    ['Connect Meta + Google', "Link your agency's accounts once; data refreshes every morning."],
    ['Goal-based results', 'Results follow the ad set goal: messages, calls, leads, purchases.'],
    ['Daily desk + alerts', 'Broken tracking, spend with no results, under-delivery, rising costs.'],
    ['Health score + targets', 'Each client scored 0–100 against budget, CPR and result targets.'],
    ['Client links + reports', 'Signed read-only links and a monthly PDF in Arabic or English.'],
  ],
  'foot' => 'Amman', 'env' => 'Staging environment',
];
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/brand/favicon-180.png">
<title>{{ $t['title'] }}</title>
<link rel="alternate" hreflang="ar" href="https://adsranger.com/">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#2DD4BF;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:{{ $ar ? '"IBM Plex Sans Arabic",' : '' }}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:1040px;margin:0 auto;padding:0 20px}
nav{display:flex;justify-content:space-between;align-items:center;padding:20px 0;gap:12px}
.logo{font-weight:700;letter-spacing:.2px;direction:ltr}.logo b{color:var(--acc)}
.nr{display:flex;gap:10px;align-items:center}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600}
.btn.o{background:transparent;border:1px solid var(--line);color:var(--ink)}
.lang{color:var(--muted);text-decoration:none;font-size:14px;padding:8px 10px}
.lang:hover{color:var(--ink)}
.hero{padding:64px 0 40px}.hero h1{font-size:clamp(30px,5vw,48px);line-height:1.3;margin:0 0 16px}
.hero p{color:var(--muted);font-size:18px;max-width:660px;margin:0 0 28px}
.tag{display:inline-block;font-size:13px;color:var(--acc);border:1px solid var(--line);padding:4px 10px;border-radius:99px;margin-bottom:18px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;padding:24px 0 56px}
.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px}
.c h3{margin:0 0 6px;font-size:16px}.c p{margin:0;color:var(--muted);font-size:14px}
footer{border-top:1px solid var(--line);padding:22px 0;color:var(--muted);font-size:13px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px}
</style>
</head>
<body>
<div class="w">
<nav><div class="logo" style="display:flex;align-items:center;gap:10px"><img src="/brand/adsranger-mark-teal.svg" alt="" width="34" height="34"><span>Ads<b>Ranger</b></span></div><div class="nr"><a class="lang" href="{{ $t['switch_url'] }}" lang="{{ $ar ? 'en' : 'ar' }}">{{ $t['switch'] }}</a><a class="btn o" href="/app/login">{{ $t['signin'] }}</a></div></nav>
<section class="hero">
<span class="tag">{{ $t['tag'] }}</span>
<h1>{{ $t['h1'] }}</h1>
<p>{{ $t['lead'] }}</p>
<a class="btn" href="/app/login">{{ $t['cta'] }}</a>
</section>
<section class="g">
@foreach ($t['f'] as $f)
<div class="c"><h3>{{ $f[0] }}</h3><p>{{ $f[1] }}</p></div>
@endforeach
</section>
<footer><span>© {{ date('Y') }} AdsRanger · {{ $t['foot'] }}</span><span><a href="{{ $ar ? '' : '/en' }}/privacy" style="color:inherit;margin-inline-end:12px">{{ $ar ? 'الخصوصية' : 'Privacy' }}</a><a href="{{ $ar ? '' : '/en' }}/terms" style="color:inherit;margin-inline-end:12px">{{ $ar ? 'الشروط' : 'Terms' }}</a><a href="{{ $ar ? '' : '/en' }}/data-deletion" style="color:inherit;margin-inline-end:12px">{{ $ar ? 'حذف البيانات' : 'Data deletion' }}</a>{{ $t['env'] }}</span></footer>
</div>
</body>
</html>
