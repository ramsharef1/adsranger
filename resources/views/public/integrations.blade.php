@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$t = $ar ? [
    'title' => 'المنصات الإعلانية قيد الربط · AdsRanger',
    'switch_url' => '/en/integrations',
    'tag' => 'قيد التطوير · ربط بصلاحية القراءة فقط',
    'h1' => 'المنصات الإعلانية التي نعمل على ربطها.',
    'lead' => 'هذه المنصات قيد التطوير: لا توجد منصة مفعّلة للوكالات بعد، وتُفتح كل منصة بعد اعتماد تطبيقنا لديها. الربط عبر واجهات المنصات الرسمية وبصلاحية القراءة فقط.',
    'networks' => [
        ['Meta Ads (Facebook & Instagram)', 'الحملات والرسائل والمكالمات والعملاء المحتملون والمبيعات حسب هدف كل حملة.'],
        ['Google Ads (Search, PMax, Display)', 'الكلمات المفتاحية والمكالمات والتحويلات.'],
        ['TikTok Ads', 'الإنفاق والمشاهدات والنتائج حسب هدف الحملة.'],
        ['Snapchat Ads', 'الإنفاق والنتائج حسب هدف الحملة، مهمة لأسواق الخليج.'],
        ['LinkedIn Ads (B2B Lead Gen)', 'العملاء المحتملون من نماذج LinkedIn والإنفاق والنتائج.'],
        ['X (Twitter) Ads', 'مزامنة حملات التفاعل، الزيارات، والتحويلات في أسواق الخليج والشرق الأوسط.'],
        ['Microsoft Advertising (Bing)', 'حملات البحث والتسوق.'],
        ['Apple Search Ads', 'تتبع تكلفة تثبيت التطبيقات والاشتراكات داخل متجر آبل.'],
        ['Amazon Ads (MENA)', 'قياس مبيعات المتاجر والعائد الإعلاني (ACOS / ROAS) على أمازون السعودية والإمارات ومصر.'],
        ['Pinterest Ads', 'مزامنة حملات التجارة الإلكترونية والأزياء والديكور.'],
        ['Reddit Ads', 'حملات المجتمعات والتقنية والزيارات المستهدفة.'],
        ['Taboola Native Ads', 'حملات المحتوى والإعلانات الضمنية في المواقع الإخبارية العربية.'],
        ['Outbrain Native Ads', 'تتبع زيارات وتحويلات المحتوى الممول عبر الناشرين.'],
    ],
    'cta' => 'انضم لقائمة الانتظار لربط حسابات وكالتك',
] : [
    'title' => 'Ad networks in development · AdsRanger',
    'switch_url' => '/integrations',
    'tag' => 'In development · read-only connections',
    'h1' => 'The ad networks we are connecting.',
    'lead' => 'These networks are in development: none is open to agencies yet, and each opens once the network approves our app. Connections use the official APIs with read-only access.',
    'networks' => [
        ['Meta Ads (Facebook & Instagram)', 'Campaigns, messages, calls, leads and sales by each campaign goal.'],
        ['Google Ads (Search, PMax, YouTube)', 'Search terms, calls and conversions.'],
        ['TikTok Ads', 'Spend, views and results by campaign goal.'],
        ['Snapchat Ads', 'Spend and results by campaign goal; key for the Gulf.'],
        ['LinkedIn Ads (B2B Lead Gen)', 'Leads from LinkedIn forms, spend and results.'],
        ['X (Twitter) Ads', 'Follower, engagement, website traffic, and conversion campaigns across MENA.'],
        ['Microsoft Advertising (Bing)', 'Search and shopping campaigns.'],
        ['Apple Search Ads', 'App Store install CPIs, tap-through rates, and in-app subscription events.'],
        ['Amazon Ads (KSA, UAE, Egypt)', 'Sponsored Products/Brands spend, attributed sales, ACOS and ROAS metrics.'],
        ['Pinterest Ads', 'Catalog shopping pins, visual discovery traffic, and e-commerce checkouts.'],
        ['Reddit Ads', 'Community targeting clicks, conversions, and tech/gaming cohort metrics.'],
        ['Taboola Native Ads', 'Native article placements across premier Arabic and global publishers.'],
        ['Outbrain Native Ads', 'Discovery feed traffic, landing page engagement, and conversion tracking.'],
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
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#5EEAD4;--line:#232c4d}
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
