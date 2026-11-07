@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$t = $ar ? [
    'title' => 'تقويم مواسم الإعلانات والتسوق في الشرق الأوسط 2026–2027 · AdsRanger',
    'switch_url' => '/en/mena-calendar',
    'tag' => 'تخطيط المواسم · الخليج والمشرق ومصر 2026–2027',
    'h1' => 'التقويم الإعلاني لمواسم الذروة في الشرق الأوسط وشمال أفريقيا.',
    'lead' => 'تتغير تكلفة الألف ظهور (CPM) وتكلفة النتيجة (CPR) بشكل كبير خلال المواسم الوطنية والدينية ومواسم التخفيضات الكبرى. خطّط ميزانيات عملائك وموادهم الإعلانية قبل كل موسم. التواريخ تقريبية وقد تتغير مواعيد المناسبات الهجرية حسب رؤية الهلال.',
    'events' => [
        ['اليوم الوطني السعودي 96', '23 سبتمبر 2026', 'ذروة التجزئة والضيافة والعطور في السعودية', 'ابدأ التجهيز: 10 سبتمبر'],
        ['الجمعة البيضاء وتخفيضات نوفمبر (White Friday)', '20 – 30 نوفمبر 2026', 'أعلى منافسة سنوية للتجارة الإلكترونية في الخليج ومصر والأردن', 'ابدأ التجهيز: 1 نوفمبر'],
        ['عيد الاتحاد الإماراتي 55 (UAE National Day)', '2 – 3 ديسمبر 2026', 'ذروة السياحة والفعاليات والتسوق في الإمارات', 'ابدأ التجهيز: 20 نوفمبر'],
        ['موسم شهر رمضان المبارك 1448 هـ', '8 فبراير – 8 مارس 2027', 'ذروة التفاعل المسائي والسحور عبر الفيديو والرسائل', 'ابدأ التجهيز: 18 يناير'],
        ['العشر الأواخر وعيد الفطر 2027', '28 فبراير – 12 مارس 2027', 'ذروة الأزياء والهدايا والحلويات والتوصيل السريع', 'ابدأ التجهيز: 20 فبراير'],
        ['موسم الحج وعيد الأضحى 2027', '10 – 20 مايو 2027', 'ذروة السفر والضيافة والأضاحي وتجارة التجزئة', 'ابدأ التجهيز: 25 أبريل'],
        ['موسم العودة إلى المدارس والجامعات', '15 أغسطس – 10 سبتمبر 2027', 'ذروة الإلكترونيات والقرطاسية والتعليم', 'ابدأ التجهيز: 1 أغسطس'],
    ],
    'cta_ramadan' => 'افتح حاسبة ميزانية رمضان 2027 التفاعلية',
    'cta_waitlist' => 'انضم لقائمة الانتظار',
] : [
    'title' => 'MENA Retail & Ad Seasonality Calendar 2026–2027 · AdsRanger',
    'switch_url' => '/mena-calendar',
    'tag' => 'MENA Seasonality · GCC, Levant & Egypt 2026–2027',
    'h1' => '2026–2027 MENA advertising & retail peak calendar for agencies.',
    'lead' => 'Auction CPMs and conversion rates shift dramatically during national holidays, White Friday, Ramadan, and Eid. Plan client budgets and creative ahead of each season. Dates are approximate; Hijri dates depend on the moon sighting.',
    'events' => [
        ['Saudi National Day 96', '23 Sep 2026', 'KSA retail, F&B, fragrances & travel surge', 'Warm-up starts: 10 Sep'],
        ['White Friday & Cyber November', '20 – 30 Nov 2026', 'Peak e-commerce auction pressure across GCC, Egypt & Jordan', 'Warm-up starts: 1 Nov'],
        ['UAE National Day (Eid Al Etihad 55)', '2 – 3 Dec 2026', 'UAE luxury retail, hospitality & events peak', 'Warm-up starts: 20 Nov'],
        ['Ramadan 1448 AH Core Flight', '8 Feb – 8 Mar 2027', 'Evening & Suhoor prime-time video & messaging surge', 'Warm-up starts: 18 Jan'],
        ['Last 10 Days & Eid al-Fitr 2027', '28 Feb – 12 Mar 2027', 'Peak gifting, fashion, beauty & express delivery cutoffs', 'Warm-up starts: 20 Feb'],
        ['Eid al-Adha & Hajj Season 2027', '10 – 20 May 2027', 'Travel, hospitality, gifting & retail conversions', 'Warm-up starts: 25 Apr'],
        ['Back to School & University', '15 Aug – 10 Sep 2027', 'Electronics, apparel & education lead generation', 'Warm-up starts: 1 Aug'],
    ],
    'cta_ramadan' => 'Open Interactive Ramadan 2027 Calculator',
    'cta_waitlist' => 'Join the Agency Waitlist',
];
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<title>{{ $t['title'] }}</title>
<link rel="alternate" hreflang="ar" href="https://adsranger.com/mena-calendar">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/mena-calendar">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#5EEAD4;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:{{ $ar ? '"IBM Plex Sans Arabic",' : '' }}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:1080px;margin:0 auto;padding:0 20px}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600}
.btn.o{background:transparent;border:1px solid var(--line);color:var(--ink)}
.tag{display:inline-block;font-size:13px;color:var(--acc);border:1px solid var(--line);padding:4px 10px;border-radius:99px;margin-bottom:14px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;padding:24px 0 40px}
.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px}
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
        @foreach ($t['events'] as $ev)
            <div class="c">
                <div style="font-size:13px;color:var(--acc);font-weight:600">{{ $ev[1] }}</div>
                <h3 style="margin:6px 0 8px;font-size:17px">{{ $ev[0] }}</h3>
                <p style="margin:0 0 10px;color:var(--muted);font-size:14px">{{ $ev[2] }}</p>
                <span style="font-size:12.5px;color:var(--ink);border:1px solid var(--line);padding:3px 9px;border-radius:8px;display:inline-block">{{ $ev[3] }}</span>
            </div>
        @endforeach
    </section>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;padding-bottom:48px">
        <a class="btn" href="{{ $base }}/ramadan-2027">{{ $t['cta_ramadan'] }}</a>
        <a class="btn o" href="{{ $base }}/waitlist">{{ $t['cta_waitlist'] }}</a>
    </div>
    @include('public.footer', ['lang' => $lang])
</div>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
