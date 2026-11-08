@php
    // Home rev 4 (Website P-2 with P-5 §7–8; UX-152). Lead line D-139 stays word for word.
    $lang = request()->segment(1) === 'en' ? 'en' : 'ar';
    $ar = $lang === 'ar';
    $arPath = '/';
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $title = $ar ? 'منصة إدارة الإعلانات للوكالات بالعربية | AdsRanger' : 'Arabic-First Ad Management Platform for Agencies | AdsRanger'; // B-01 (Content 03:22)
    $meta = $ar
        ? 'مساحة عمل عربية أولًا من AdsRanger، قيد التطوير، ستتيح لوكالات الإعلان في المنطقة إدارة إعلانات كل عملائها وتقاريرها في مكان واحد. الإطلاق في 2027.'
        : 'AdsRanger is the Arabic-first workspace, now in development, where MENA agencies will run and report every client\'s ads in one place. Launching in 2027.';
    $schema = [
        ['@type' => 'Organization', 'name' => 'AdsRanger', 'url' => 'https://adsranger.com/', 'logo' => 'https://adsranger.com/brand/icon-512.png'],
        ['@type' => 'WebSite', 'name' => 'AdsRanger', 'url' => \App\Support\PublicUrl::absolute('/', $lang), 'inLanguage' => $lang],
    ];
    $cards = $ar ? [
        ['النتائج حسب الهدف', 'نتيجة كل حملة تتبع هدفها: الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات.', 'قيد التطوير', false],
        ['المكتب اليومي والتنبيهات', 'التتبّع المعطّل، والإنفاق دون نتائج، وضعف التسليم، وارتفاع التكلفة، كلها تظهر كل صباح.', 'قيد التطوير', false],
        ['مؤشر الصحة والمستهدفات', 'تقييم كل عميل من 0 إلى 100 مقابل الميزانية وتكلفة النتيجة والمستهدفات.', 'قيد التطوير', false],
        ['روابط وتقارير للعملاء', 'روابط للقراءة فقط لعملائك، وتقرير شهري بالعربية أو الإنجليزية.', 'قيد التطوير', false],
        ['استوديو الإعلانات', 'أنشئ الحملات ونصوص الإعلانات لعدة منصات من موجز واحد، بموافقتك قبل نشر أي شيء.', 'مخطط له', true],
    ] : [
        ['Results by goal', 'Each campaign\'s result follows its goal: messages, calls, leads, bookings, sales.', 'In development', false],
        ['Daily desk and alerts', 'Broken tracking, spend with no results, under-delivery and rising costs, flagged every morning.', 'In development', false],
        ['Health score and targets', 'Each client scored from 0 to 100 against budget, cost per result and targets.', 'In development', false],
        ['Client links and reports', 'Read-only links for clients and a monthly report in Arabic or English.', 'In development', false],
        ['Ads Studio', 'Build campaigns and ad copy for several platforms from one brief, with your approval before anything is published.', 'Planned', true],
    ];
    $cardMicros = $ar ? [
        ['الرسائل', 'المكالمات', 'العملاء المحتملون', 'الحجوزات', 'المبيعات'],
        ['حرج · تتبّع معطّل', 'تحذير · ارتفاع التكلفة'],
        ['مؤشر الصحة · 0–100'],
        ['عربي / EN · للقراءة فقط'],
        ['موجز واحد ← موافقتك قبل النشر'],
    ] : [
        ['Messages', 'Calls', 'Leads', 'Bookings', 'Sales'],
        ['Critical · Broken tracking', 'Warning · Rising cost'],
        ['Health score · 0–100'],
        ['AR / EN · Read-only link'],
        ['One brief → Your approval first'],
    ];
    $networks = ['Meta', 'Google Ads', 'TikTok', 'Snapchat', 'Microsoft Advertising', 'LinkedIn', 'X'];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.hero-split{display:grid;grid-template-columns:1fr;gap:28px;align-items:center;padding-block:32px 24px}
@media (min-width:960px){.hero-split{grid-template-columns:1.12fr .88fr;gap:36px;padding-block:44px 28px}}
.hero .lead{font-size:18px;color:var(--muted);margin:0 0 14px;line-height:1.75}
.hero .status{display:inline-flex;align-items:center;gap:8px;font-size:13.5px;font-weight:700;color:var(--info-ink);background:var(--info-bg);border:1px solid var(--line);padding:5px 14px;border-radius:99px;margin:0 0 16px}
.hero .status::before{content:"";width:8px;height:8px;border-radius:99px;background:var(--acc);display:inline-block}
.hero .actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:18px}
.hero .ramadan-pill{display:inline-flex;align-items:center;gap:8px;min-height:44px;margin-top:12px;padding:6px 14px;border-radius:10px;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);color:var(--ink);font-size:14px;font-weight:600;text-decoration:none}
.hero .ramadan-pill:hover{background:rgba(245,158,11,.2)}
.hero .ramadan-dot{width:8px;height:8px;border-radius:99px;background:#f59e0b;display:inline-block}
/* Semrush x AdsRanger Cockpit Window */
.ar-cockpit{background:var(--surface);border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:0 16px 36px -14px rgba(11,17,32,.18)}
.ar-cockpit-bar{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 14px;background:var(--card);border-bottom:1px solid var(--line);font-size:12.5px;font-weight:600}
.ar-dots{display:inline-flex;gap:5px;align-items:center}
.ar-dots i{width:9px;height:9px;border-radius:99px;display:inline-block;background:var(--line)}
.ar-dots i:nth-child(1){background:#ef4444;opacity:.75}
.ar-dots i:nth-child(2){background:#f59e0b;opacity:.75}
.ar-dots i:nth-child(3){background:#10b981;opacity:.75}
.ar-cockpit-body{padding:16px;display:grid;gap:12px}
.ar-mock-row{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 14px}
.ar-mock-label{font-size:12px;font-weight:700;color:var(--muted);margin-bottom:8px;display:flex;align-items:center;justify-content:space-between}
.ar-goal-chips{display:flex;flex-wrap:wrap;gap:6px}
.ar-goal-chips span{font-size:12px;font-weight:600;padding:3px 9px;border-radius:6px;background:var(--info-bg);color:var(--info-ink)}
.ar-meter-track{height:8px;border-radius:99px;background:var(--neu-bg);overflow:hidden;margin-top:6px}
.ar-meter-fill{height:100%;width:78%;background:linear-gradient(90deg,var(--acc),#f59e0b);border-radius:99px}
.ar-alert-strip{display:flex;flex-wrap:wrap;gap:6px}
.ar-alert-pill{font-size:12px;font-weight:600;padding:3px 9px;border-radius:6px;background:rgba(245,158,11,.14);color:var(--ink);border:1px solid rgba(245,158,11,.35)}
/* Bento Toolkit Cards */
.sec-head{display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:8px;margin:28px 0 14px}
.sec-head h2{margin:0}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr));gap:16px;list-style:none;padding:0;margin:0}
.cards .card{display:flex;flex-direction:column;justify-content:space-between;gap:12px;position:relative;overflow:hidden;border-top:3px solid var(--acc)}
.cards .card.card-planned{border-top-color:#f59e0b}
.cards .card-head{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.cards p{margin:6px 0 0;color:var(--muted);font-size:15px}
.card-micro{display:flex;flex-wrap:wrap;gap:6px;padding-top:10px;border-top:1px dashed var(--line);font-size:12px;font-weight:600;color:var(--muted)}
.card-micro span{padding:2px 8px;border-radius:6px;background:var(--surface);border:1px solid var(--line)}
/* Planned Integrations Bar */
.net-box{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:20px;margin-top:28px}
.net-box h2{margin:0 0 12px}
.pills{display:flex;flex-wrap:wrap;gap:8px;list-style:none;padding:0;margin:0}
.pills li{display:inline-flex;align-items:center;gap:7px;background:var(--card);border:1px solid var(--line);border-radius:99px;padding:7px 14px;font-size:14px;font-weight:600}
.pills li::before{content:"";width:7px;height:7px;border-radius:99px;background:var(--acc);display:inline-block}
.text-link{display:inline-flex;align-items:center;min-height:44px;text-decoration:underline;font-weight:600}
</style>
@endpush

@section('content')
<div class="hero-split">
    <section class="hero">
        <p class="status">{{ $ar ? 'قيد التطوير · الإطلاق في 2027' : 'In development · launching 2027' }}</p>
        <h1>{{ $ar ? 'تقارير إعلانات كل عملائك في مساحة عمل واحدة' : 'Every client\'s ad reporting in one workspace' }}</h1>
        <p class="lead">{!! $ar
            ? 'منصة تقارير <bdi dir="ltr">Meta</bdi>، <bdi dir="ltr">Google</bdi> للوكالات الإعلانية: الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات تُحسب حسب الهدف الحقيقي لكل حملة، مع تنبيهات يومية تكشف هدر الميزانية، وتقارير بالعربية والإنجليزية يفهمها عملاؤك.'
            : 'Meta and Google Ads reporting for agencies: messages, calls, leads, bookings and sales counted by each campaign\'s real goal, with daily alerts that catch wasted spend and Arabic/English reports your clients understand.' !!}</p>
        <div class="actions">
            <a class="btn" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a>
            <a class="btn btn-ghost" href="{{ $u('/demo-report') }}">{{ $ar ? 'شاهد تقريرًا تجريبيًا' : 'See a sample report' }}</a>
        </div>
        <div>
            <a class="ramadan-pill text-link" href="{{ $u('/ramadan-2027') }}">
                <span class="ramadan-dot" aria-hidden="true"></span>
                <span>{{ $ar ? 'اقرأ دليل رمضان 2027' : 'Read the Ramadan 2027 guide' }}</span>
            </a>
        </div>
    </section>

    <aside class="ar-cockpit" aria-label="{{ $ar ? 'معاينة واجهة المكتب اليومي (قيد التطوير)' : 'Daily desk interface preview (in development)' }}">
        <div class="ar-cockpit-bar">
            <span class="ar-dots" aria-hidden="true"><i></i><i></i><i></i></span>
            <span>AdsRanger · {{ $ar ? 'المكتب اليومي' : 'Daily desk' }}</span>
            <span class="badge">{{ $ar ? 'معاينة · قيد التطوير' : 'Preview · In development' }}</span>
        </div>
        <div class="ar-cockpit-body">
            <div class="ar-mock-row">
                <div class="ar-mock-label">
                    <span>{{ $ar ? 'النتائج حسب الهدف' : 'Results by goal' }}</span>
                    <span><bdi dir="ltr">Meta · Google Ads</bdi></span>
                </div>
                <div class="ar-goal-chips">
                    @foreach ($cardMicros[0] as $goalChip)
                        <span>{{ $goalChip }}</span>
                    @endforeach
                </div>
            </div>
            <div class="ar-mock-row">
                <div class="ar-mock-label">
                    <span>{{ $ar ? 'المكتب اليومي والتنبيهات' : 'Daily desk and alerts' }}</span>
                    <span>{{ $ar ? 'ملخص الصباح' : 'Morning check' }}</span>
                </div>
                <div class="ar-alert-strip">
                    @foreach ($cardMicros[1] as $alertChip)
                        <span class="ar-alert-pill">{{ $alertChip }}</span>
                    @endforeach
                </div>
            </div>
            <div class="ar-mock-row">
                <div class="ar-mock-label">
                    <span>{{ $ar ? 'مؤشر الصحة والمستهدفات' : 'Health score and targets' }}</span>
                    <span style="font-variant-numeric:tabular-nums">0–100</span>
                </div>
                <div class="ar-meter-track" aria-hidden="true"><div class="ar-meter-fill"></div></div>
            </div>
        </div>
    </aside>
</div>

<section aria-labelledby="h-build">
    <div class="sec-head">
        <h2 id="h-build">{{ $ar ? 'ما نبنيه لوكالتك' : 'What we\'re building for your agency' }}</h2>
    </div>
    <ul class="cards">
        @foreach ($cards as $idx => [$cardTitle, $body, $badge, $planned])
            <li class="card {{ $planned ? 'card-planned' : '' }}">
                <div>
                    <div class="card-head">
                        <h3>{{ $cardTitle }}</h3>
                        <span class="badge {{ $planned ? 'badge-neutral' : '' }}">{{ $badge }}</span>
                    </div>
                    <p>{{ $body }}</p>
                </div>
                <div class="card-micro" aria-hidden="true">
                    @foreach ($cardMicros[$idx] ?? [] as $chip)
                        <span>{{ $chip }}</span>
                    @endforeach
                </div>
            </li>
        @endforeach
    </ul>
</section>

<section class="net-box" aria-labelledby="h-nets">
    <h2 id="h-nets">{{ $ar ? 'تكاملات مخطط لها' : 'Planned integrations' }}</h2>
    <ul class="pills">
        @foreach ($networks as $network)
            <li><bdi dir="ltr">{{ $network }}</bdi></li>
        @endforeach
    </ul>
    <p style="margin:12px 0 0"><a class="text-link" href="{{ $u('/integrations') }}">{{ $ar ? 'كل المنصات الإعلانية المخطط لها' : 'All planned ad platforms' }}</a></p>
</section>
@endsection
