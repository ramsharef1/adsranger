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
    $goals = $ar ? ['الرسائل', 'المكالمات', 'العملاء المحتملون', 'الحجوزات', 'المبيعات'] : ['Messages', 'Calls', 'Leads', 'Bookings', 'Sales'];
    $networks = ['Meta', 'Google Ads', 'TikTok', 'Snapchat', 'Microsoft Advertising', 'LinkedIn', 'X'];
@endphp
@extends('layouts.public')

@push('styles')
<style>
/* Deep Indigo-Violet Centered Hero + Overlapping Product Showcase */
.hp-hero{width:100vw;margin-inline:calc(50% - 50vw);margin-top:-8px;padding:52px 16px 110px;background:radial-gradient(950px 420px at 50% 0%,rgba(109,40,217,.32),transparent 70%),radial-gradient(700px 340px at 85% 20%,rgba(45,212,191,.18),transparent 65%),linear-gradient(180deg,#0f172a 0%,#191636 58%,#111827 100%);color:#ffffff;text-align:center}
.hp-hero-in{max-width:900px;margin:0 auto}
.hp-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;border-radius:99px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);color:#5eead4;font-size:13.5px;font-weight:700;margin-bottom:18px}
.hp-pill i{width:8px;height:8px;border-radius:99px;background:var(--acc);display:inline-block}
.hp-hero h1{color:#ffffff;font-size:clamp(32px,5vw,52px);line-height:1.18;letter-spacing:-.02em;margin:0 auto 16px;max-width:820px}
.hp-hero .lead{color:#cbd5e1;font-size:clamp(16.5px,2vw,19px);line-height:1.75;max-width:780px;margin:0 auto 28px}
/* Elevated Command Action Bar */
.hp-cmdbar{max-width:680px;margin:0 auto 18px;background:#ffffff;border-radius:14px;padding:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;box-shadow:0 22px 48px -12px rgba(0,0,0,.55)}
.hp-cmd-preview{display:flex;align-items:center;gap:10px;padding:8px 14px;color:#0f172a;font-size:14.5px;font-weight:600;flex:1;min-width:200px;text-align:start}
.hp-nets span{white-space:nowrap}
.hp-cmd-dot{width:10px;height:10px;border-radius:99px;background:#0f766e;flex-shrink:0}
.hp-cta-main{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:12px 26px;border-radius:10px;background:var(--btn);color:var(--btn-ink);font-weight:700;font-size:16px;text-decoration:none;white-space:nowrap}
.hp-cta-main:hover{opacity:.92;color:var(--btn-ink)}
.hp-cta-sec{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:10px 16px;border-radius:10px;background:#f1f5f9;color:#0f172a;font-weight:600;font-size:14.5px;text-decoration:none;white-space:nowrap}
.hp-cta-sec:hover{background:#e2e8f0;color:#0f172a}
.hp-sublinks{display:flex;justify-content:center;gap:18px;flex-wrap:wrap;margin-top:12px;font-size:14px}
.hp-sublinks a{color:#fde68a;font-weight:600;text-decoration:underline;min-height:44px;display:inline-flex;align-items:center;gap:6px}
/* Overlapping White Product Board */
.hp-showcase{max-width:1140px;margin:-76px auto 44px;background:var(--card);border:1px solid var(--line);border-radius:20px;box-shadow:0 24px 60px -15px rgba(15,23,42,.18);overflow:hidden}
.hp-tabs{display:flex;overflow-x:auto;background:var(--surface);border-bottom:1px solid var(--line);padding:10px 14px;gap:8px}
.hp-tab{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;font-size:13.5px;font-weight:700;white-space:nowrap;background:var(--card);border:1px solid var(--line);color:var(--ink)}
.hp-tab-active{background:#0f172a;color:#ffffff;border-color:#0f172a}
.hp-tab-num{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:6px;background:var(--info-bg);color:var(--acc);font-size:11.5px;font-weight:700}
.hp-board{display:grid;grid-template-columns:1fr;gap:18px;padding:24px}
@media (min-width:920px){.hp-board{grid-template-columns:300px 1fr}}
/* Left Donut Gauge & Health Widget */
.hp-widget{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;gap:14px}
.hp-w-hd{display:flex;align-items:center;justify-content:space-between;font-size:12.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.03em}
.hp-donut-wrap{display:flex;align-items:center;gap:16px}
.hp-donut{width:84px;height:84px;flex-shrink:0}
.hp-score-big{font-size:28px;font-weight:700;line-height:1.1;font-variant-numeric:tabular-nums}
.hp-score-sub{font-size:12.5px;color:var(--muted);margin-top:4px}
/* Right Campaign Goal & Alert Table */
.hp-table-card{background:var(--surface);border:1px solid var(--line);border-radius:14px;overflow:hidden}
.hp-table-top{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:12px 16px;border-bottom:1px solid var(--line);background:var(--card)}
.hp-goal-bar{display:flex;flex-wrap:wrap;gap:6px;padding:14px 16px;border-bottom:1px solid var(--line)}
.hp-goal-pill{padding:5px 12px;border-radius:8px;background:var(--card);border:1px solid var(--line);font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px}
.hp-goal-pill::before{content:"";width:7px;height:7px;border-radius:99px;background:#0f766e;display:inline-block}
.hp-alert-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;padding:14px 16px}
.hp-alert-box{background:var(--card);border:1px solid var(--line);border-inline-start:4px solid var(--acc);border-radius:10px;padding:10px 12px;font-size:13px;font-weight:600}
/* 5-Card Toolkit Grid */
.hp-sec-title{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:20px 0 18px}
.hp-sec-title h2{margin:0;font-size:clamp(24px,3.2vw,30px)}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr));gap:18px;list-style:none;padding:0;margin:0}
.cards .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;display:flex;flex-direction:column;justify-content:space-between;gap:14px;box-shadow:0 4px 16px -6px rgba(15,23,42,.07);transition:transform .15s ease,box-shadow .15s ease}
.cards .card:hover{transform:translateY(-2px);box-shadow:0 14px 28px -10px rgba(15,23,42,.14)}
.cards .card-head{display:flex;align-items:center;justify-content:space-between;gap:10px}
.hp-ico{width:38px;height:38px;border-radius:10px;background:rgba(255,100,45,.12);color:var(--acc);display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:14px}
.cards h3{font-size:19.5px;margin:6px 0}
.cards p{margin:0;color:var(--muted);font-size:15px;line-height:1.65}
/* Planned Integrations Strip */
.hp-nets{margin-top:40px;background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
.hp-nets h2{margin:0 0 10px;font-size:21px}
.pills{display:flex;flex-wrap:wrap;gap:8px;list-style:none;padding:0;margin:0}
.pills li{display:inline-flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--line);border-radius:10px;padding:8px 14px;font-size:14px;font-weight:700;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.pills li::before{content:"";width:8px;height:8px;border-radius:99px;background:var(--acc);display:inline-block}
.text-link{display:inline-flex;align-items:center;min-height:44px;text-decoration:underline;font-weight:700;color:var(--acc)}
</style>
@endpush

@section('content')
<section class="hp-hero">
    <div class="hp-hero-in">
        <p class="hp-pill"><i aria-hidden="true"></i><span>{{ $ar ? 'قيد التطوير · الإطلاق في 2027' : 'In development · launching 2027' }}</span></p>
        <h1>{{ $ar ? 'تقارير إعلانات كل عملائك في مساحة عمل واحدة' : 'Every client\'s ad reporting in one workspace' }}</h1>
        <p class="lead">{!! $ar
            ? 'منصة تقارير <bdi dir="ltr">Meta</bdi>، <bdi dir="ltr">Google</bdi> للوكالات الإعلانية: الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات تُحسب حسب الهدف الحقيقي لكل حملة، مع تنبيهات يومية تكشف هدر الميزانية، وتقارير بالعربية والإنجليزية يفهمها عملاؤك.'
            : 'Meta and Google Ads reporting for agencies: messages, calls, leads, bookings and sales counted by each campaign\'s real goal, with daily alerts that catch wasted spend and Arabic/English reports your clients understand.' !!}</p>

        <div class="hp-cmdbar">
            <div class="hp-cmd-preview">
                <span class="hp-cmd-dot" aria-hidden="true"></span>
                <span>{{ $ar ? 'المنصات المخطط لها:' : 'Planned platforms:' }} <bdi dir="ltr" class="hp-nets">@foreach (['Meta', 'Google Ads', 'TikTok', 'Snapchat'] as $i => $n)@if ($i) · @endif<span>{{ $n }}</span>@endforeach</bdi></span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="hp-cta-sec" href="{{ $u('/demo-report') }}">{{ $ar ? 'شاهد تقريرًا تجريبيًا' : 'See a sample report' }}</a>
                <a class="hp-cta-main" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a>
            </div>
        </div>

        <div class="hp-sublinks">
            <a href="{{ $u('/ramadan-2027') }}">{{ $ar ? 'اقرأ دليل رمضان 2027' : 'Read the Ramadan 2027 guide' }}</a>
        </div>
    </div>
</section>

<div class="hp-showcase" role="region" aria-label="{{ $ar ? 'معاينة المنصة (قيد التطوير)' : 'Platform preview (in development)' }}">
    <div class="hp-tabs" aria-hidden="true">
        @foreach ($cards as $idx => [$cardTitle])
            <span class="hp-tab {{ $idx === 0 ? 'hp-tab-active' : '' }}">
                <span class="hp-tab-num">0{{ $idx + 1 }}</span>
                <span>{{ $cardTitle }}</span>
            </span>
        @endforeach
    </div>

    <div class="hp-board">
        <div class="hp-widget">
            <div class="hp-w-hd">
                <span>{{ $ar ? 'مؤشر الصحة والمستهدفات' : 'Health score and targets' }}</span>
                <span class="badge">{{ $ar ? 'قيد التطوير' : 'In development' }}</span>
            </div>
            <div class="hp-donut-wrap">
                <svg class="hp-donut" viewBox="0 0 36 36" aria-hidden="true">
                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3.5"/>
                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#0f766e" stroke-width="3.5" stroke-dasharray="85, 100" stroke-linecap="round"/>
                </svg>
                <div>
                    <div class="hp-score-big">0–100</div>
                    <div class="hp-score-sub">{{ $ar ? 'مقابل الميزانية وتكلفة النتيجة' : 'Against budget & cost per result' }}</div>
                </div>
            </div>
        </div>

        <div class="hp-table-card">
            <div class="hp-table-top">
                <strong style="font-size:14px">{{ $ar ? 'النتائج حسب الهدف · المكتب اليومي' : 'Results by goal · Daily desk' }}</strong>
                <span class="badge">{{ $ar ? 'معاينة · قيد التطوير' : 'Preview · In development' }}</span>
            </div>
            <div class="hp-goal-bar">
                @foreach ($goals as $g)
                    <span class="hp-goal-pill">{{ $g }}</span>
                @endforeach
            </div>
            <div class="hp-alert-grid">
                <div class="hp-alert-box">{{ $ar ? 'التتبّع المعطّل والإنفاق دون نتائج' : 'Broken tracking & spend with no results' }}</div>
                <div class="hp-alert-box" style="border-inline-start-color:#0f766e">{{ $ar ? 'روابط للقراءة فقط وتقرير شهري (عربي / EN)' : 'Read-only client links & monthly AR/EN report' }}</div>
            </div>
        </div>
    </div>
</div>

<section aria-labelledby="h-build">
    <div class="hp-sec-title">
        <h2 id="h-build">{{ $ar ? 'ما نبنيه لوكالتك' : 'What we\'re building for your agency' }}</h2>
    </div>
    <ul class="cards">
        @foreach ($cards as $idx => [$cardTitle, $body, $badge, $planned])
            <li class="card">
                <div>
                    <div class="card-head">
                        <span class="hp-ico" aria-hidden="true">0{{ $idx + 1 }}</span>
                        <span class="badge {{ $planned ? 'badge-neutral' : '' }}">{{ $badge }}</span>
                    </div>
                    <h3>{{ $cardTitle }}</h3>
                    <p>{{ $body }}</p>
                </div>
            </li>
        @endforeach
    </ul>
</section>

<section class="hp-nets" aria-labelledby="h-nets">
    <div>
        <h2 id="h-nets">{{ $ar ? 'تكاملات مخطط لها' : 'Planned integrations' }}</h2>
        <ul class="pills">
            @foreach ($networks as $network)
                <li><bdi dir="ltr">{{ $network }}</bdi></li>
            @endforeach
        </ul>
    </div>
    <a class="text-link" href="{{ $u('/integrations') }}">{{ $ar ? 'كل المنصات الإعلانية المخطط لها' : 'All planned ad platforms' }}</a>
</section>
@endsection
