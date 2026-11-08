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
    $cardAccents = ['#14b8a6', '#f97316', '#10b981', '#38bdf8', '#f59e0b'];
    $networks = ['Meta', 'Google Ads', 'TikTok', 'Snapchat', 'Microsoft Advertising', 'LinkedIn', 'X'];
@endphp
@extends('layouts.public')

@push('styles')
<style>
/* Full-bleed Semrush x AdsRanger Dark Command Hero */
.sr-hero-bleed{width:100vw;margin-inline:calc(50% - 50vw);margin-top:-8px;padding-block:44px 52px;background:radial-gradient(900px 420px at 80% 15%,rgba(45,212,191,.18),transparent 65%),radial-gradient(700px 380px at 15% 85%,rgba(249,115,22,.14),transparent 60%),linear-gradient(135deg,#090d1a 0%,#111a36 52%,#0d2e33 100%);color:#f8fafc;border-bottom:1px solid #1e293b}
.sr-hero-grid{max-width:1200px;margin-inline:auto;padding-inline:16px;display:grid;grid-template-columns:1fr;gap:32px;align-items:center}
@media (min-width:768px){.sr-hero-grid{padding-inline:24px}}
@media (min-width:980px){.sr-hero-grid{grid-template-columns:1.05fr .95fr;gap:40px}}
.sr-status{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#5eead4;background:rgba(45,212,191,.12);border:1px solid rgba(45,212,191,.35);padding:5px 14px;border-radius:99px;margin:0 0 16px}
.sr-status::before{content:"";width:8px;height:8px;border-radius:99px;background:#2dd4bf;box-shadow:0 0 0 4px rgba(45,212,191,.2)}
.sr-hero-bleed h1{color:#ffffff;font-size:clamp(30px,4.5vw,46px);line-height:1.2;letter-spacing:-.02em;margin:0 0 14px}
.sr-hero-bleed .lead{font-size:17.5px;color:#cbd5e1;margin:0 0 22px;line-height:1.75}
.sr-actions{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.btn-sr-cta{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:12px 24px;border-radius:10px;background:linear-gradient(135deg,#f97316 0%,#ea580c 100%);color:#ffffff;font-weight:700;font-size:16px;text-decoration:none;box-shadow:0 10px 24px -6px rgba(249,115,22,.55);border:0}
.btn-sr-cta:hover{opacity:.95;color:#ffffff}
.btn-sr-ghost{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:12px 20px;border-radius:10px;background:rgba(255,255,255,.07);color:#f8fafc;border:1px solid rgba(255,255,255,.22);font-weight:600;font-size:15.5px;text-decoration:none}
.btn-sr-ghost:hover{background:rgba(255,255,255,.14);color:#ffffff}
.sr-ramadan-link{display:inline-flex;align-items:center;gap:8px;min-height:44px;margin-top:14px;padding:6px 14px;border-radius:8px;background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.4);color:#fde68a;font-size:14px;font-weight:600;text-decoration:none}
.sr-ramadan-link:hover{background:rgba(245,158,11,.22)}
/* Semrush Analytics Cockpit Window */
.sr-window{background:#0b1120;border:1px solid #26334d;border-radius:16px;overflow:hidden;box-shadow:0 24px 48px -12px rgba(0,0,0,.6)}
.sr-win-top{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 16px;background:#111827;border-bottom:1px solid #1e293b;font-size:12.5px;color:#cbd5e1}
.sr-win-dots{display:inline-flex;gap:6px}
.sr-win-dots i{width:10px;height:10px;border-radius:99px;display:inline-block}
.sr-win-dots i:nth-child(1){background:#f87171}
.sr-win-dots i:nth-child(2){background:#fbbf24}
.sr-win-dots i:nth-child(3){background:#34d399}
.sr-win-tag{padding:2px 10px;border-radius:99px;background:rgba(45,212,191,.15);color:#5eead4;font-weight:700;font-size:11.5px;border:1px solid rgba(45,212,191,.3)}
.sr-win-grid{display:grid;grid-template-columns:1fr;gap:12px;padding:16px}
.sr-kpi-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.sr-kpi-box{background:#111827;border:1px solid #1e293b;border-radius:10px;padding:10px 12px}
.sr-kpi-lbl{font-size:11px;color:#94a3b8;font-weight:600}
.sr-kpi-val{font-size:18px;font-weight:700;color:#f8fafc;margin-top:2px;font-variant-numeric:tabular-nums}
.sr-panel{background:#111827;border:1px solid #1e293b;border-radius:10px;padding:12px}
.sr-panel-hd{display:flex;align-items:center;justify-content:space-between;font-size:12px;font-weight:700;color:#cbd5e1;margin-bottom:8px}
.sr-chips{display:flex;flex-wrap:wrap;gap:6px}
.sr-chip{font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:6px;background:rgba(20,184,166,.15);color:#5eead4;border:1px solid rgba(20,184,166,.3)}
.sr-chip-warn{background:rgba(249,115,22,.16);color:#fdba74;border-color:rgba(249,115,22,.35)}
.sr-gauge-bar{height:8px;border-radius:99px;background:#1e293b;overflow:hidden;margin-top:6px}
.sr-gauge-fill{height:100%;width:84%;background:linear-gradient(90deg,#14b8a6,#f97316);border-radius:99px}
/* Semrush Toolkit Bento Matrix */
.sr-sec-hd{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:40px 0 18px}
.sr-sec-hd h2{margin:0;font-size:clamp(22px,3.2vw,28px)}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr));gap:18px;list-style:none;padding:0;margin:0}
.cards .card{display:flex;flex-direction:column;justify-content:space-between;gap:14px;padding:22px;border-radius:16px;box-shadow:0 8px 24px -12px rgba(11,17,32,.1)}
.sr-card-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
.sr-num{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;background:var(--info-bg);color:var(--info-ink);font-weight:700;font-size:13px;font-variant-numeric:tabular-nums}
.cards h3{font-size:19px;margin:8px 0 6px}
.cards p{margin:0;color:var(--muted);font-size:15px}
.sr-micro-strip{display:flex;flex-wrap:wrap;gap:6px;padding-top:12px;border-top:1px solid var(--line)}
.sr-micro-strip span{font-size:12px;font-weight:600;padding:3px 9px;border-radius:6px;background:var(--surface);border:1px solid var(--line);color:var(--ink)}
/* Semrush Dark Command Bar for Integrations */
.sr-net-banner{margin-top:36px;padding:26px;border-radius:18px;background:linear-gradient(135deg,#0b1120 0%,#131f3a 100%);color:#f8fafc;border:1px solid #1e293b}
.sr-net-banner h2{margin:0 0 14px;color:#ffffff}
.pills{display:flex;flex-wrap:wrap;gap:8px;list-style:none;padding:0;margin:0}
.pills li{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16);color:#f8fafc;border-radius:99px;padding:7px 15px;font-size:14px;font-weight:600}
.pills li::before{content:"";width:8px;height:8px;border-radius:99px;background:#2dd4bf;display:inline-block}
.sr-net-banner .text-link{color:#5eead4;display:inline-flex;align-items:center;min-height:44px;text-decoration:underline;font-weight:600;margin-top:12px}
</style>
@endpush

@section('content')
<div class="sr-hero-bleed">
    <div class="sr-hero-grid">
        <section class="hero">
            <p class="sr-status">{{ $ar ? 'قيد التطوير · الإطلاق في 2027' : 'In development · launching 2027' }}</p>
            <h1>{{ $ar ? 'تقارير إعلانات كل عملائك في مساحة عمل واحدة' : 'Every client\'s ad reporting in one workspace' }}</h1>
            <p class="lead">{!! $ar
                ? 'منصة تقارير <bdi dir="ltr">Meta</bdi>، <bdi dir="ltr">Google</bdi> للوكالات الإعلانية: الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات تُحسب حسب الهدف الحقيقي لكل حملة، مع تنبيهات يومية تكشف هدر الميزانية، وتقارير بالعربية والإنجليزية يفهمها عملاؤك.'
                : 'Meta and Google Ads reporting for agencies: messages, calls, leads, bookings and sales counted by each campaign\'s real goal, with daily alerts that catch wasted spend and Arabic/English reports your clients understand.' !!}</p>
            <div class="sr-actions">
                <a class="btn-sr-cta" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a>
                <a class="btn-sr-ghost" href="{{ $u('/demo-report') }}">{{ $ar ? 'شاهد تقريرًا تجريبيًا' : 'See a sample report' }}</a>
            </div>
            <div>
                <a class="sr-ramadan-link" href="{{ $u('/ramadan-2027') }}">
                    <span aria-hidden="true">🌙</span>
                    <span>{{ $ar ? 'اقرأ دليل رمضان 2027' : 'Read the Ramadan 2027 guide' }}</span>
                </a>
            </div>
        </section>

        <aside class="sr-window" aria-label="{{ $ar ? 'معاينة المكتب اليومي (قيد التطوير)' : 'Daily desk preview (in development)' }}">
            <div class="sr-win-top">
                <span class="sr-win-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                <span style="font-weight:700">AdsRanger · {{ $ar ? 'المكتب اليومي' : 'Daily desk' }}</span>
                <span class="sr-win-tag">{{ $ar ? 'قيد التطوير' : 'In development' }}</span>
            </div>
            <div class="sr-win-grid">
                <div class="sr-kpi-strip" aria-hidden="true">
                    <div class="sr-kpi-box" style="border-top:2px solid #14b8a6">
                        <div class="sr-kpi-lbl">{{ $ar ? 'النتائج حسب الهدف' : 'Results by goal' }}</div>
                        <div class="sr-kpi-val">5 {{ $ar ? 'أهداف' : 'Goals' }}</div>
                    </div>
                    <div class="sr-kpi-box" style="border-top:2px solid #f97316">
                        <div class="sr-kpi-lbl">{{ $ar ? 'تنبيهات الصباح' : 'Morning alerts' }}</div>
                        <div class="sr-kpi-val">24/7</div>
                    </div>
                    <div class="sr-kpi-box" style="border-top:2px solid #10b981">
                        <div class="sr-kpi-lbl">{{ $ar ? 'مؤشر الصحة' : 'Health score' }}</div>
                        <div class="sr-kpi-val">0–100</div>
                    </div>
                </div>

                <div class="sr-panel">
                    <div class="sr-panel-hd">
                        <span>{{ $ar ? 'النتائج حسب الهدف' : 'Results by goal' }}</span>
                        <span style="color:#5eead4"><bdi dir="ltr">Meta · Google Ads</bdi></span>
                    </div>
                    <div class="sr-chips">
                        @foreach ($cardMicros[0] as $goalChip)
                            <span class="sr-chip">{{ $goalChip }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="sr-panel">
                    <div class="sr-panel-hd">
                        <span>{{ $ar ? 'المكتب اليومي والتنبيهات' : 'Daily desk and alerts' }}</span>
                        <span style="color:#fdba74">{{ $ar ? 'فحص يومي' : 'Daily check' }}</span>
                    </div>
                    <div class="sr-chips">
                        @foreach ($cardMicros[1] as $alertChip)
                            <span class="sr-chip sr-chip-warn">{{ $alertChip }}</span>
                        @endforeach
                    </div>
                    <div class="sr-gauge-bar" aria-hidden="true"><div class="sr-gauge-fill"></div></div>
                </div>
            </div>
        </aside>
    </div>
</div>

<section aria-labelledby="h-build">
    <div class="sr-sec-hd">
        <h2 id="h-build">{{ $ar ? 'ما نبنيه لوكالتك' : 'What we\'re building for your agency' }}</h2>
    </div>
    <ul class="cards">
        @foreach ($cards as $idx => [$cardTitle, $body, $badge, $planned])
            <li class="card" style="border-top:4px solid {{ $cardAccents[$idx] ?? 'var(--acc)' }}">
                <div>
                    <div class="sr-card-top">
                        <span class="sr-num" aria-hidden="true">0{{ $idx + 1 }}</span>
                        <span class="badge {{ $planned ? 'badge-neutral' : '' }}">{{ $badge }}</span>
                    </div>
                    <h3>{{ $cardTitle }}</h3>
                    <p>{{ $body }}</p>
                </div>
                <div class="sr-micro-strip" aria-hidden="true">
                    @foreach ($cardMicros[$idx] ?? [] as $chip)
                        <span>{{ $chip }}</span>
                    @endforeach
                </div>
            </li>
        @endforeach
    </ul>
</section>

<section class="sr-net-banner" aria-labelledby="h-nets">
    <h2 id="h-nets">{{ $ar ? 'تكاملات مخطط لها' : 'Planned integrations' }}</h2>
    <ul class="pills">
        @foreach ($networks as $network)
            <li><bdi dir="ltr">{{ $network }}</bdi></li>
        @endforeach
    </ul>
    <p style="margin:8px 0 0"><a class="text-link" href="{{ $u('/integrations') }}">{{ $ar ? 'كل المنصات الإعلانية المخطط لها' : 'All planned ad platforms' }}</a></p>
</section>
@endsection
