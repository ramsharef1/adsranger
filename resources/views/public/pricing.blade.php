@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$t = $ar ? [
    'title' => 'الأسعار والباقات للوكالات · AdsRanger',
    'switch_url' => '/en/pricing',
    'tag' => 'باقات شفافة للوكالات · بدون عمولة على الإنفاق الإعلاني',
    'h1' => 'باقات مصممة لنمو وكالتك الإعلانية في الشرق الأوسط.',
    'lead' => 'ثمن ثابت وواضح حسب عدد العملاء الذين تديرهم وكالتك، دون أي نسبة من ميزانيات عملائك الإعلانية عبر 18 شبكة.',
    'plans' => [
        [
            'name' => 'باقة الانطلاق (Starter)',
            'price' => '$99',
            'period' => '/ شهرياً',
            'desc' => 'للوكالات الناشئة ومديري الإعلانات المستقلين.',
            'items' => [
                'حتى 5 عملاء نشطين',
                'ربط ميتا وجوجل وتيك توك وسناب شات',
                'نتائج حسب الهدف الحقيقي للحملة',
                'المكتب اليومي و 18 قاعدة تنبيه',
                'روابط تقارير العملاء للقراءة فقط + PDF شهري',
            ],
        ],
        [
            'name' => 'باقة النمو (Growth)',
            'price' => '$249',
            'period' => '/ شهرياً',
            'desc' => 'للوكالات المتنامية التي تدير حملات متعددة القنوات.',
            'badge' => 'الأكثر اختياراً',
            'items' => [
                'حتى 20 عميلاً نشطاً',
                'ربط جميع الـ 18 شبكة إعلانية + فحص CAPI EMQ',
                'استوديو الإعلانات وتوليد النصوص باللهجات العربية',
                'جدولة المحتوى العضوي وصندوق الوارد الموحد',
                'تقارير مجدولة عبر البريد الإلكتروني وواتساب',
            ],
        ],
        [
            'name' => 'باقة الوكالة المتقدمة (Agency Pro)',
            'price' => '$499',
            'period' => '/ شهرياً',
            'desc' => 'للوكالات الكبرى التي تحتاج ذكاء العائد المدمج وعمليات المالية.',
            'items' => [
                'عملاء غير محدودين وأعضاء فريق غير محدودين',
                'مركز الذكاء: العائد المدمج (MER) ومصفوفة الكلمات والخطاف النفسي',
                'مركز عمليات الوكالة (CFO): مدرج الميزانية، ربحية العقود، وإعادة التوزيع التلقائي',
                'تتبع عائد حملات المؤثرين وأكواد الخصم',
                'نطاق مخصص بالكامل باسم وكالتك (White-Label Custom Domain)',
            ],
        ],
    ],
    'cta_waitlist' => 'احجز مقعد وكالتك في قائمة الانتظار',
    'cta_demo' => 'شاهد التقرير التجريبي التفاعلي',
] : [
    'title' => 'Agency Pricing & Plans · AdsRanger',
    'switch_url' => '/pricing',
    'tag' => 'Flat agency pricing · Zero % tax on your ad spend',
    'h1' => 'Predictable plans built for MENA marketing agencies.',
    'lead' => 'Scale your client roster across 18 ad networks without paying a percentage of managed media spend.',
    'plans' => [
        [
            'name' => 'Starter Agency',
            'price' => '$99',
            'period' => '/ month',
            'desc' => 'For boutique agencies and performance media buyers.',
            'items' => [
                'Up to 5 active client workspaces',
                'Meta, Google, TikTok & Snapchat connectors',
                'Goal-based results (messages, calls, leads, sales)',
                'Daily Desk + 18 automated alert rules',
                'Signed read-only client links + monthly PDF reports',
            ],
        ],
        [
            'name' => 'Growth Agency',
            'price' => '$249',
            'period' => '/ month',
            'desc' => 'For multi-channel agencies scaling creative & social ops.',
            'badge' => 'Most Popular',
            'items' => [
                'Up to 20 active client workspaces',
                'All 18 ad network connectors + CAPI/Pixel EMQ diagnostics',
                'Creative Studio + AI copy in Gulf, Levantine, Egyptian & MSA',
                'Organic Social scheduler, calendar & sentiment inbox',
                'Automated Email & WhatsApp client report delivery',
            ],
        ],
        [
            'name' => 'Agency Pro',
            'price' => '$499',
            'period' => '/ month',
            'desc' => 'Full intelligence, CFO operations & white-label portal.',
            'items' => [
                'Unlimited clients & unlimited team seats',
                'Intelligence Hub: Blended ROAS/MER, AI Keyword Matrix & Hook Scoring',
                'Agency CFO Hub: Budget runway, retainer margin & auto-reallocation',
                'Influencer campaign & promo-code ROI attribution',
                'White-label custom agency domain with automated SSL',
            ],
        ],
    ],
    'cta_waitlist' => 'Reserve your agency spot on the waitlist',
    'cta_demo' => 'Explore Interactive Demo Report',
];
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<title>{{ $t['title'] }}</title>
<link rel="alternate" hreflang="ar" href="https://adsranger.com/pricing">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/pricing">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#2DD4BF;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:{{ $ar ? '"IBM Plex Sans Arabic",' : '' }}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:1080px;margin:0 auto;padding:0 20px}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600}
.btn.o{background:transparent;border:1px solid var(--line);color:var(--ink)}
.tag{display:inline-block;font-size:13px;color:var(--acc);border:1px solid var(--line);padding:4px 10px;border-radius:99px;margin-bottom:14px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;padding:24px 0 48px}
.c{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px;display:flex;flex-direction:column;justify-content:space-between}
.c.hi{border-color:var(--acc);box-shadow:0 0 0 1px var(--acc)}
ul{padding-inline-start:20px;margin:16px 0 24px;color:var(--muted);font-size:14.5px}
li{margin-bottom:8px}
</style>
</head>
<body>
<div class="w">
    @include('public.nav', ['lang' => $lang, 'switch_url' => $t['switch_url']])
    <section style="padding:36px 0 12px">
        <span class="tag">{{ $t['tag'] }}</span>
        <h1 style="font-size:clamp(28px,4.5vw,44px);margin:0 0 12px">{{ $t['h1'] }}</h1>
        <p style="color:var(--muted);font-size:17px;max-width:740px;margin:0">{{ $t['lead'] }}</p>
    </section>
    <section class="g">
        @foreach ($t['plans'] as $p)
            <div class="c {{ !empty($p['badge']) ? 'hi' : '' }}">
                <div>
                    @if (!empty($p['badge']))
                        <span class="tag" style="margin-bottom:10px">{{ $p['badge'] }}</span>
                    @endif
                    <h2 style="margin:0 0 6px;font-size:20px">{{ $p['name'] }}</h2>
                    <p style="color:var(--muted);font-size:14px;margin:0 0 14px">{{ $p['desc'] }}</p>
                    <div style="margin-bottom:14px">
                        <strong dir="ltr" style="font-size:34px;color:var(--acc)">{{ $p['price'] }}</strong>
                        <span style="color:var(--muted);font-size:14px">{{ $p['period'] }}</span>
                    </div>
                    <ul>
                        @foreach ($p['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                <a class="btn {{ empty($p['badge']) ? 'o' : '' }}" style="text-align:center" href="{{ $base }}/waitlist">{{ $t['cta_waitlist'] }}</a>
            </div>
        @endforeach
    </section>
    <div style="text-align:center;padding-bottom:48px">
        <a class="btn o" href="{{ $base }}/demo-report">{{ $t['cta_demo'] }}</a>
    </div>
    @include('public.footer', ['lang' => $lang])
</div>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
