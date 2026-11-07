@php
$ar = ($lang ?? 'ar') === 'ar';
$base = $ar ? '' : '/en';
$t = $ar ? [
    'title' => 'نموذج تقرير العميل التفاعلي · AdsRanger',
    'switch_url' => '/en/demo-report',
    'tag' => 'تقرير تجريبي ببيانات نموذجية · هكذا سيرى عملاؤك نتائجهم',
    'h1' => 'تقرير عميل واضح، بالنتيجة الحقيقية لكل حملة.',
    'lead' => 'بدلاً من جداول البيانات المعقدة، يحصل عميلك على رابط آمن للقراءة فقط وتقرير PDF شهري يعرض الإنفاق والرسائل والمكالمات والمبيعات ومؤشر صحة الحساب.',
    'client_name' => 'مجموعة النخبة الطبية — الرياض (بيانات تجريبية)',
    'period' => 'الفترة: آخر 30 يوماً · العملة: USD',
    'kpis' => [
        ['الإنفاق الإعلاني', '$18,450', 'ضمن الميزانية المستهدفة ($19,000)'],
        ['إجمالي النتائج (حسب الهدف)', '2,340', 'رسائل واتساب + مكالمات + حجوزات عيادة'],
        ['متوسط تكلفة النتيجة (CPR)', '$7.88', 'أفضل بنسبة 12% من الهدف ($9.00)'],
        ['مؤشر صحة العميل (Health Score)', '88 / 100', 'ممتاز · جودة تتبع CAPI EMQ 8.6/10'],
        ['العائد الإعلاني المدمج (Blended MER)', '4.2x', 'صافي المبيعات المنسوبة: $77,490'],
    ],
    'table_title' => 'أداء الحملات حسب الهدف الحقيقي',
    'cols' => ['الحملة الإعلانية', 'الشبكة', 'نوع النتيجة (الهدف)', 'النتائج', 'التكلفة للنتيجة', 'الإنفاق'],
    'rows' => [
        ['حملة حجوزات الليزر والجلدية — الخليج', 'Meta Ads', 'رسائل واتساب فورية', '1,120 رسالة', '$6.10', '$6,832'],
        ['حملة البحث عن زراعة الأسنان — الرياض', 'Google Ads', 'مكالمات هاتفية مؤكدة (>60 ثانية)', '485 مكالمة', '$11.40', '$5,529'],
        ['عروض العناية بالبشرة — فيديو تفاعلي', 'TikTok Ads', 'حجوزات موقع إلكتروني', '410 حجز', '$8.90', '$3,649'],
        ['عدسة وعروض الابتسامة — سناب شات', 'Snapchat Ads', 'طلبات استشارة سريعة', '325 عميل محتمل', '$7.50', '$2,440'],
    ],
    'cta' => 'أنشئ تقارير مماثلة لعملاء وكالتك — انضم لقائمة الانتظار',
] : [
    'title' => 'Interactive Sample Client Report · AdsRanger',
    'switch_url' => '/demo-report',
    'tag' => 'Sample report with demo data · what your clients will see',
    'h1' => 'Client reporting that speaks in real business outcomes.',
    'lead' => 'Replace confusing spreadsheets with a signed, read-only Arabic/English client portal and monthly PDF showing goal-matched results, health scores, and top creatives.',
    'client_name' => 'Al-Nokhba Medical Group — Riyadh (Sample Demo Data)',
    'period' => 'Period: Last 30 Days · Currency: USD',
    'kpis' => [
        ['Total Ad Spend', '$18,450', 'On pace against $19,000 monthly target'],
        ['Goal-Matched Results', '2,340', 'WhatsApp messages + qualified calls + bookings'],
        ['Blended Cost Per Result', '$7.88', '12% below target CPR ($9.00)'],
        ['Client Health Score', '88 / 100', 'Healthy · CAPI Event Match Quality 8.6/10'],
        ['Blended ROAS / MER', '4.2x', 'Attributed clinic revenue: $77,490'],
    ],
    'table_title' => 'Campaign Breakdown by Real Ad-Set Goal',
    'cols' => ['Campaign', 'Network', 'Result Type (Goal)', 'Results', 'Cost / Result', 'Spend'],
    'rows' => [
        ['Dermatology & Laser Bookings — GCC', 'Meta Ads', 'WhatsApp Conversations', '1,120 msgs', '$6.10', '$6,832'],
        ['Dental Implant High-Intent Search — Riyadh', 'Google Ads', 'Qualified Phone Calls (>60s)', '485 calls', '$11.40', '$5,529'],
        ['Skincare Spark Video Flight', 'TikTok Ads', 'Website Clinic Bookings', '410 bookings', '$8.90', '$3,649'],
        ['Hollywood Smile Story & Lens Flight', 'Snapchat Ads', 'Instant Lead Forms', '325 leads', '$7.50', '$2,440'],
    ],
    'cta' => 'Deliver reports like this to your clients — Join Waitlist',
];
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
<title>{{ $t['title'] }}</title>
<link rel="alternate" hreflang="ar" href="https://adsranger.com/demo-report">
<link rel="alternate" hreflang="en" href="https://adsranger.com/en/demo-report">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b1020;--card:#121933;--ink:#eef1fb;--muted:#9aa3c0;--acc:#5EEAD4;--line:#232c4d}
*{box-sizing:border-box}body{margin:0;font-family:{!! $ar ? '"IBM Plex Sans Arabic",' : '' !!}system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:{{ $ar ? '1.8' : '1.55' }}}
.w{max-width:1080px;margin:0 auto;padding:0 20px}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--acc);color:#0B1020;text-decoration:none;font-weight:600}
.tag{display:inline-block;font-size:13px;color:var(--acc);border:1px solid var(--line);padding:4px 10px;border-radius:99px;margin-bottom:14px}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:20px 0}
.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{padding:12px 10px;border-bottom:1px solid var(--line);text-align:{{ $ar ? 'right' : 'left' }}}
th{color:var(--muted);font-weight:600;font-size:13px}
</style>
</head>
<body>
<div class="w">
    @include('public.nav', ['lang' => $lang, 'switch_url' => $t['switch_url']])
    <section style="padding:32px 0 12px">
        <span class="tag">{{ $t['tag'] }}</span>
        <h1 style="font-size:clamp(26px,4.5vw,42px);margin:0 0 10px">{{ $t['h1'] }}</h1>
        <p style="color:var(--muted);font-size:17px;max-width:760px;margin:0">{{ $t['lead'] }}</p>
    </section>
    <section class="c" style="margin:20px 0 36px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-bottom:1px solid var(--line);padding-bottom:14px">
            <strong style="font-size:18px">{{ $t['client_name'] }}</strong>
            <span style="color:var(--muted);font-size:13.5px">{{ $t['period'] }}</span>
        </div>
        <div class="g">
            @foreach ($t['kpis'] as $k)
                <div style="background:#0b1020;border:1px solid var(--line);border-radius:12px;padding:14px">
                    <div style="font-size:12.5px;color:var(--muted)">{{ $k[0] }}</div>
                    <strong dir="ltr" style="font-size:22px;color:var(--acc);display:block;margin:4px 0">{{ $k[1] }}</strong>
                    <div style="font-size:12px;color:var(--muted)">{{ $k[2] }}</div>
                </div>
            @endforeach
        </div>
        <h3 style="margin:22px 0 10px;font-size:16px">{{ $t['table_title'] }}</h3>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        @foreach ($t['cols'] as $col)
                            <th>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($t['rows'] as $row)
                        <tr>
                            <td><strong>{{ $row[0] }}</strong></td>
                            <td dir="ltr">{{ $row[1] }}</td>
                            <td style="color:var(--acc)">{{ $row[2] }}</td>
                            <td>{{ $row[3] }}</td>
                            <td dir="ltr">{{ $row[4] }}</td>
                            <td dir="ltr">{{ $row[5] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <div style="text-align:center;padding-bottom:48px">
        <a class="btn" href="{{ $base }}/waitlist">{{ $t['cta'] }}</a>
    </div>
    @include('public.footer', ['lang' => $lang])
</div>
@include('public.cookie-banner', ['lang' => $lang])
</body>
</html>
