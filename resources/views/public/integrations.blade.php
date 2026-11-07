@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$t = $ar ? [
    'title' => 'دليل الـ 18 شبكة إعلانية والتكاملات · AdsRanger',
    'switch_url' => '/en/integrations',
    'tag' => '18 شبكة إعلانية · فحص البكسل و CAPI · صلاحيات آمنة',
    'h1' => 'ربط موحد لجميع قنوات الإعلانات المدفوعة والعضوية في الشرق الأوسط.',
    'lead' => 'يرتبط AdsRanger عبر بروتوكول OAuth الرسمي مع 18 شبكة إعلانية ومنصة تواصل، ليجمع الإنفاق والرسائل والمكالمات والمبيعات وجودة التتبع (EMQ) في لوحة واحدة.',
    'networks' => [
        ['Meta Ads (Facebook & Instagram)', 'مزامنة الحملات، رسائل واتساب وإنستغرام، المكالمات، العملاء المحتملين، المبيعات، وفحص جودة CAPI EMQ.'],
        ['Google Ads (Search, PMax, Display)', 'مزامنة الكلمات المفتاحية، مكالمات الهاتف، التحويلات، ومصفوفة نيات البحث بالذكاء الاصطناعي.'],
        ['TikTok Ads & Spark Ads', 'تتبع الفيديو، تفاعل Spark Ads، المبيعات، وتحليل سرعة الخطاف الإعلاني في الثواني الثلاث الأولى.'],
        ['Snapchat Ads & AR Lenses', 'منصة أساسية لأسواق الخليج: تتبع المبيعات، الرسائل، وعائد عدسات الواقع المعزز (AR Lens ROI).'],
        ['LinkedIn Ads (B2B Lead Gen)', 'تتبع نماذج العملاء المحتملين للشركات وتقييم جودة الشركات حسب القطاع وعدد الموظفين.'],
        ['X (Twitter) Ads', 'مزامنة حملات التفاعل، الزيارات، والتحويلات في أسواق الخليج والشرق الأوسط.'],
        ['Microsoft Advertising (Bing)', 'حملات البحث والتسوق مع توزيع الكلمات المفتاحية المشترك مع جوجل.'],
        ['YouTube Ads', 'قياس حملات الفيديو، المشاهدات الكاملة، والتأثير على البحث والعائد المدمج.'],
        ['WhatsApp Business Cloud API', 'قياس محادثات الإعلانات الموجهة لواتساب وإرسال تقارير العملاء المجدولة تلقائياً.'],
        ['Apple Search Ads', 'تتبع تكلفة تثبيت التطبيقات والاشتراكات داخل متجر آبل.'],
        ['Amazon Ads (MENA)', 'قياس مبيعات المتاجر والعائد الإعلاني (ACOS / ROAS) على أمازون السعودية والإمارات ومصر.'],
        ['Pinterest Ads', 'مزامنة حملات التجارة الإلكترونية والأزياء والديكور.'],
        ['Reddit Ads', 'حملات المجتمعات والتقنية والزيارات المستهدفة.'],
        ['Taboola Native Ads', 'حملات المحتوى والإعلانات الضمنية في المواقع الإخبارية العربية.'],
        ['Outbrain Native Ads', 'تتبع زيارات وتحويلات المحتوى الممول عبر الناشرين.'],
        ['Criteo Commerce Media', 'حملات إعادة الاستهداف الديناميكي لمتاجر التجارة الإلكترونية.'],
        ['Telegram Ads', 'تتبع القنوات والإعلانات الممولة في تيليجرام.'],
        ['Shahid / MBC Connected TV', 'قياس حملات البث الرقمي والفيديو المميز في رمضان والمواسم الذروة.'],
    ],
    'cta' => 'انضم لقائمة الانتظار لربط حسابات وكالتك',
] : [
    'title' => '18 Ad Networks & API Integrations Directory · AdsRanger',
    'switch_url' => '/integrations',
    'tag' => '18 Native Connectors · CAPI & Pixel EMQ · OAuth 2.0 Security',
    'h1' => 'One unified schema across 18 paid, social & commerce networks.',
    'lead' => 'AdsRanger connects via official OAuth 2.0 APIs to normalize spend, goal-based conversions (messages, calls, leads, purchases), and pixel Event Match Quality every morning.',
    'networks' => [
        ['Meta Ads (Facebook & Instagram)', 'Campaigns, Click-to-WhatsApp/IG messages, calls, lead forms, purchases, and CAPI Event Match Quality (EMQ).'],
        ['Google Ads (Search, PMax, YouTube)', 'Search terms, call extensions, conversions, and AI search intent keyword clustering.'],
        ['TikTok Ads & Spark Ads', 'Video hook velocity, Spark Ads organic boost triggers, and pixel/Events API diagnostics.'],
        ['Snapchat Ads & AR Lenses', 'Built for GCC scale: story ads, dynamic catalogs, and AR Lens swipe-to-checkout ROI attribution.'],
        ['LinkedIn Ads (B2B Lead Gen)', 'Native lead gen forms and automated B2B company size & industry lead scoring.'],
        ['X (Twitter) Ads', 'Follower, engagement, website traffic, and conversion campaigns across MENA.'],
        ['Microsoft Advertising (Bing)', 'Search & shopping campaigns with bilingual AI keyword mapping matrices.'],
        ['YouTube Video & Shorts', 'TrueView views, CPV/CPM pacing, and cross-channel blended MER impact.'],
        ['WhatsApp Business Cloud API', 'Click-to-WhatsApp conversion tracking and scheduled client report delivery.'],
        ['Apple Search Ads', 'App Store install CPIs, tap-through rates, and in-app subscription events.'],
        ['Amazon Ads (KSA, UAE, Egypt)', 'Sponsored Products/Brands spend, attributed sales, ACOS and ROAS metrics.'],
        ['Pinterest Ads', 'Catalog shopping pins, visual discovery traffic, and e-commerce checkouts.'],
        ['Reddit Ads', 'Community targeting clicks, conversions, and tech/gaming cohort metrics.'],
        ['Taboola Native Ads', 'Native article placements across premier Arabic and global publishers.'],
        ['Outbrain Native Ads', 'Discovery feed traffic, landing page engagement, and conversion tracking.'],
        ['Criteo Commerce Media', 'Dynamic retargeting catalogs and repeat-buyer ROAS tracking.'],
        ['Telegram Sponsored Messages', 'Channel reach, clicks, and subscriber acquisition tracking.'],
        ['Shahid / MBC Connected TV', 'Premium MENA OTT streaming impressions and Ramadan prime-time reach.'],
    ],
    'cta' => 'Join the Agency Waitlist to connect your accounts',
];
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<title>{{ $t['title'] }}</title>
<link rel="alternate" hreflang="ar" href="https://adsranger.com/integrations">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/integrations">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#2DD4BF;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:{{ $ar ? '"IBM Plex Sans Arabic",' : '' }}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:1080px;margin:0 auto;padding:0 20px}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600}
.tag{display:inline-block;font-size:13px;color:var(--acc);border:1px solid var(--line);padding:4px 10px;border-radius:99px;margin-bottom:14px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:14px;padding:24px 0 40px}
.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px}
.c h3{margin:0 0 6px;font-size:16px;color:var(--acc);direction:ltr;text-align:{{ $ar ? 'right' : 'left' }}}.c p{margin:0;color:var(--muted);font-size:14px}
</style>
</head>
<body>
<div class="w">
    @include('public.nav', ['lang' => $lang, 'switch_url' => $t['switch_url']])
    <section style="padding:36px 0 12px">
        <span class="tag">{{ $t['tag'] }}</span>
        <h1 style="font-size:clamp(28px,4.5vw,44px);margin:0 0 12px">{{ $t['h1'] }}</h1>
        <p style="color:var(--muted);font-size:17px;max-width:760px;margin:0">{{ $t['lead'] }}</p>
    </section>
    <section class="g">
        @foreach ($t['networks'] as $net)
            <div class="c">
                <h3>{{ $net[0] }}</h3>
                <p>{{ $net[1] }}</p>
            </div>
        @endforeach
    </section>
    <div style="text-align:center;padding-bottom:48px">
        <a class="btn" href="{{ $base }}/waitlist">{{ $t['cta'] }}</a>
    </div>
    @include('public.footer', ['lang' => $lang])
</div>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
