@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$t = $ar ? [
    'title' => 'دليل وحاسبة حملات رمضان 2027 للوكالات · AdsRanger',
    'switch' => 'English', 'switch_url' => '/en/ramadan-2027',
    'home' => 'الرئيسية', 'waitlist' => 'قائمة الانتظار', 'signin' => 'تسجيل الدخول',
    'tag' => 'تخطيط المواسم · رمضان 1448 هـ (8 فبراير – 9 مارس 2027)',
    'h1' => 'خطة ميزانية وحملات رمضان 2027 لوكالات الشرق الأوسط.',
    'lead' => 'يُتوقع أن يبدأ شهر رمضان القادم يوم 8 فبراير 2027 ويستمر حتى عيد الفطر في 9 مارس 2027 (حسب تقويم أم القرى، وقد يختلف يومًا حسب رؤية الهلال). قد ترتفع المنافسة وتكلفة الظهور حسب السوق والقطاع، مع ذروة التفاعل في ساعات المساء والسحور.',
    'phases' => [
        ['1. مرحلة التجهيز والتشويق (18 يناير – 7 فبراير 2027)', 'اختبار الإعلانات، فحص البكسل وصفحات الهبوط، وبناء جماهير إعادة الاستهداف قبل ارتفاع تكلفة الألف ظهور.'],
        ['2. ذروة شهر رمضان (8 فبراير – 8 مارس 2027)', 'رفع الميزانيات اليومية بنسبة +20%، التركيز على حملات المبيعات والرسائل المسائية، ومراقبة إجهاد الإعلانات.'],
        ['3. العشر الأواخر وعيد الفطر (28 فبراير – 11 مارس 2027)', 'ذروة الهدايا والأزياء والمطاعم؛ تحويل الرسائل الإعلانية إلى عروض العيد والشحن السريع.'],
    ],
    'calc_title' => 'حاسبة ميزانية رمضان 2027 التفاعلية',
    'calc_sub' => 'تقدير مبدئي بافتراضات قابلة للتعديل: ميزانية مقترحة ونتائج متوقعة خلال 30 يومًا. الافتراضات المبدئية (+20% إنفاق، +10% تكلفة النتيجة) ليست بيانات سوق؛ عدّلها حسب حساباتك.',
    'lbl_uplift' => 'زيادة الإنفاق في رمضان (%)',
    'lbl_shift' => 'تغيّر تكلفة النتيجة (%)',
    'lbl_daily' => 'متوسط الإنفاق اليومي المعتاد (USD)',
    'lbl_cpr' => 'تكلفة النتيجة المعتادة (CPR USD)',
    'out_budget' => 'ميزانية رمضان المقترحة (30 يومًا)',
    'out_cpr' => 'تكلفة النتيجة المتوقعة في رمضان',
    'out_results' => 'النتائج المتوقعة خلال الشهر',
    'cta_waitlist' => 'انضم إلى قائمة الانتظار',
    'foot' => 'عمّان',
] : [
    'title' => 'Ramadan 2027 Agency Campaign Planner · AdsRanger',
    'switch' => 'العربية', 'switch_url' => '/ramadan-2027',
    'home' => 'Home', 'waitlist' => 'Waitlist', 'signin' => 'Sign in',
    'tag' => 'MENA Seasonality · Ramadan 1448 AH (8 Feb – 9 Mar 2027)',
    'h1' => 'Ramadan 2027 media planning & budget calculator for MENA agencies.',
    'lead' => 'Ramadan 2027 is expected to begin on 8 February 2027 and run until Eid al-Fitr on 9 March 2027 (Umm al-Qura calendar; it may shift by a day depending on the moon sighting). Evening and late-night traffic rises, and auction costs may rise depending on the market and sector.',
    'phases' => [
        ['1. Warm-up & pixel check (18 Jan – 7 Feb 2027)', 'Validate conversion tracking, landing page speed and creative variants while CPMs are still at baseline.'],
        ['2. Ramadan core flight (8 Feb – 8 Mar 2027)', 'Scale daily budgets +20% for evening/Suhoor prime hours and watch frequency alerts closely.'],
        ['3. Last 10 days & Eid al-Fitr (28 Feb – 11 Mar 2027)', 'Peak gifting, fashion and retail conversions; pivot ad copy to Eid delivery cutoffs and offers.'],
    ],
    'calc_title' => 'Interactive Ramadan 2027 Budget Calculator',
    'calc_sub' => 'An initial estimate with assumptions you can change: a suggested 30-day budget and projected results. The starting assumptions (+20% spend, +10% cost per result) are not market data; adjust them to your accounts.',
    'lbl_uplift' => 'Ramadan spend increase (%)',
    'lbl_shift' => 'Cost per result change (%)',
    'lbl_daily' => 'Normal daily spend (USD)',
    'lbl_cpr' => 'Normal cost per result (CPR USD)',
    'out_budget' => 'Recommended 30-day Ramadan budget',
    'out_cpr' => 'Projected Ramadan CPR',
    'out_results' => 'Projected 30-day results',
    'cta_waitlist' => 'Join the Agency Waitlist',
    'foot' => 'Amman',
];
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<title>{{ $t['title'] }}</title>
<link rel="alternate" hreflang="ar" href="https://adsranger.com/ramadan-2027">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/ramadan-2027">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#5EEAD4;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:{{ $ar ? '"IBM Plex Sans Arabic",' : '' }}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:1040px;margin:0 auto;padding:0 20px}
nav{display:flex;justify-content:space-between;align-items:center;padding:20px 0;gap:12px;flex-wrap:wrap}
.logo{font-weight:700;letter-spacing:.2px;direction:ltr;color:var(--ink);text-decoration:none;display:flex;align-items:center;gap:10px}.logo b{color:var(--acc)}
.nr{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;min-height:44px;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600}
.btn.o{background:transparent;border:1px solid var(--line);color:var(--ink)}
.lang{color:var(--muted);text-decoration:none;font-size:14px;padding:8px 10px}
.tag{display:inline-block;font-size:13px;color:var(--acc);border:1px solid var(--line);padding:4px 10px;border-radius:99px;margin-bottom:14px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;padding:18px 0 32px}
.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px}
.c h3{margin:0 0 8px;font-size:16px;color:var(--acc)}.c p{margin:0;color:var(--muted);font-size:14px}
input{width:100%;min-height:44px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#0b1020;color:var(--ink);font-size:15px}
footer{border-top:1px solid var(--line);padding:22px 0;color:var(--muted);font-size:13px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px}
</style>
</head>
<body>
<div class="w">
@include('public.nav', ['lang' => $lang, 'switch_url' => $t['switch_url']])
<section style="padding:36px 0 12px">
    <span class="tag">{{ $t['tag'] }}</span>
    <h1 style="font-size:clamp(28px,4.5vw,44px);margin:0 0 14px">{{ $t['h1'] }}</h1>
    <p style="color:var(--muted);font-size:17px;max-width:740px;margin:0 0 22px">{!! $t['lead'] !!}</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn" href="{{ $base }}/waitlist">{{ $t['cta_waitlist'] }}</a>
    </div>
</section>
<section class="g">
    @foreach ($t['phases'] as $p)
        <div class="c"><h3>{{ $p[0] }}</h3><p>{{ $p[1] }}</p></div>
    @endforeach
</section>
<section class="c" style="margin-bottom:48px">
    <h2 style="margin:0 0 6px;font-size:20px">{{ $t['calc_title'] }}</h2>
    <p style="color:var(--muted);font-size:14px;margin:0 0 18px">{{ $t['calc_sub'] }}</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;align-items:end">
        <div>
            <label for="rm-daily" style="display:block;font-size:13px;margin-bottom:6px">{{ $t['lbl_daily'] }}</label>
            <input id="rm-daily" type="number" min="1" step="10" value="100" dir="ltr">
        </div>
        <div>
            <label for="rm-cpr" style="display:block;font-size:13px;margin-bottom:6px">{{ $t['lbl_cpr'] }}</label>
            <input id="rm-cpr" type="number" min="0.1" step="0.5" value="5" dir="ltr">
        </div>
        <div>
            <label for="rm-uplift" style="display:block;font-size:13px;margin-bottom:6px">{{ $t['lbl_uplift'] }}</label>
            <input id="rm-uplift" type="number" min="-50" max="200" step="5" value="20" dir="ltr">
        </div>
        <div>
            <label for="rm-shift" style="display:block;font-size:13px;margin-bottom:6px">{{ $t['lbl_shift'] }}</label>
            <input id="rm-shift" type="number" min="-50" max="200" step="5" value="10" dir="ltr">
        </div>
        <div style="background:#0b1020;border:1px solid var(--line);border-radius:12px;padding:12px">
            <div style="font-size:12px;color:var(--muted)">{{ $t['out_budget'] }}</div>
            <strong id="rm-out-budget" dir="ltr" style="font-size:20px;color:var(--acc)">3,600 USD</strong>
        </div>
        <div style="background:#0b1020;border:1px solid var(--line);border-radius:12px;padding:12px">
            <div style="font-size:12px;color:var(--muted)">{{ $t['out_cpr'] }}</div>
            <strong id="rm-out-cpr" dir="ltr" style="font-size:20px">5.50 USD</strong>
        </div>
        <div style="background:#0b1020;border:1px solid var(--line);border-radius:12px;padding:12px">
            <div style="font-size:12px;color:var(--muted)">{{ $t['out_results'] }}</div>
            <strong id="rm-out-res" dir="ltr" style="font-size:20px">655</strong>
        </div>
    </div>
</section>
@include('public.footer', ['lang' => $lang])
</div>
<script>
(function(){
    var d = document.getElementById('rm-daily');
    var c = document.getElementById('rm-cpr');
    var ob = document.getElementById('rm-out-budget');
    var oc = document.getElementById('rm-out-cpr');
    var or = document.getElementById('rm-out-res');
    var up = document.getElementById('rm-uplift');
    var sh = document.getElementById('rm-shift');
    function pct(el){ var v = parseFloat(el.value); return isNaN(v) ? 0 : v / 100; }
    function calc(){
        var daily = Math.max(0, parseFloat(d.value) || 0);
        var cpr = Math.max(0.01, parseFloat(c.value) || 1);
        var budget = Math.round(daily * 30 * Math.max(0, 1 + pct(up)));
        var ramCpr = Math.max(0.01, cpr * (1 + pct(sh)));
        var res = Math.round(budget / ramCpr);
        ob.textContent = budget.toLocaleString('en-US') + ' USD';
        oc.textContent = ramCpr.toFixed(2) + ' USD';
        or.textContent = res.toLocaleString('en-US');
    }
    if (d && c) {
        [d, c, up, sh].forEach(function(el){ el.addEventListener('input', calc); });
    }
})();
</script>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
