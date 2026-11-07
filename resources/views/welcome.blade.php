@php
    // Home rev 4 (Website P-2 with P-5 §7–8; UX-152). Lead line D-139 stays word for word.
    $lang = request()->segment(1) === 'en' ? 'en' : 'ar';
    $ar = $lang === 'ar';
    $arPath = '/';
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $title = $ar ? 'AdsRanger: مساحة عمل إعلانية عربية لوكالات المنطقة' : 'AdsRanger: Arabic-First Ad Workspace for MENA Agencies';
    $meta = $ar
        ? 'AdsRanger مساحة عمل عربية أولًا، قيد التطوير، ستتيح لوكالات الإعلان في المنطقة إدارة إعلانات كل عملائها وتقاريرها في مكان واحد. الإطلاق في 2027.'
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
    $networks = ['Meta', 'Google Ads', 'TikTok', 'Snapchat', 'Microsoft Advertising', 'LinkedIn', 'X'];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.hero{padding-block:40px 16px;max-width:860px}
.hero .lead{font-size:18px;color:var(--muted);margin:0 0 10px}
.hero .status{font-size:14px;font-weight:600;color:var(--acc);margin:0 0 20px}
.hero .actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr));gap:14px;list-style:none;padding:0;margin:0}
.cards p{margin:8px 0 0;color:var(--muted)}
.pills{display:flex;flex-wrap:wrap;gap:8px;list-style:none;padding:0;margin:0}
.pills li{border:1px solid var(--line);border-radius:99px;padding:6px 14px;font-size:14px}
.text-link{display:inline-flex;align-items:center;min-height:44px;text-decoration:underline}
</style>
@endpush

@section('content')
<section class="hero">
    <h1>{{ $ar ? 'تقارير إعلانات كل عملائك في مساحة عمل واحدة' : 'Every client\'s ad reporting in one workspace' }}</h1>
    <p class="lead">{!! $ar
        ? 'منصة تقارير <bdi dir="ltr">Meta</bdi>، <bdi dir="ltr">Google</bdi> للوكالات الإعلانية: الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات تُحسب حسب الهدف الحقيقي لكل حملة، مع تنبيهات يومية تكشف هدر الميزانية، وتقارير بالعربية والإنجليزية يفهمها عملاؤك.'
        : 'Meta and Google Ads reporting for agencies: messages, calls, leads, bookings and sales counted by each campaign\'s real goal, with daily alerts that catch wasted spend and Arabic/English reports your clients understand.' !!}</p>
    <p class="status">{{ $ar ? 'قيد التطوير · الإطلاق في 2027' : 'In development · launching 2027' }}</p>
    <div class="actions">
        <a class="btn" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a>
        <a class="btn btn-ghost" href="{{ $u('/demo-report') }}">{{ $ar ? 'شاهد تقريرًا تجريبيًا' : 'See a sample report' }}</a>
    </div>
    <p><a class="text-link" href="{{ $u('/ramadan-2027') }}">{{ $ar ? 'اقرأ دليل رمضان 2027' : 'Read the Ramadan 2027 guide' }}</a></p>
</section>

<section aria-labelledby="h-build">
    <h2 id="h-build">{{ $ar ? 'ما نبنيه لوكالتك' : 'What we\'re building for your agency' }}</h2>
    <ul class="cards">
        @foreach ($cards as [$cardTitle, $body, $badge, $planned])
            <li class="card">
                <h3>{{ $cardTitle }}</h3>
                <span class="badge {{ $planned ? 'badge-neutral' : '' }}">{{ $badge }}</span>
                <p>{{ $body }}</p>
            </li>
        @endforeach
    </ul>
</section>

<section aria-labelledby="h-nets">
    <h2 id="h-nets">{{ $ar ? 'تكاملات مخطط لها' : 'Planned integrations' }}</h2>
    <ul class="pills">
        @foreach ($networks as $network)
            <li><bdi dir="ltr">{{ $network }}</bdi></li>
        @endforeach
    </ul>
    <p><a class="text-link" href="{{ $u('/integrations') }}">{{ $ar ? 'كل المنصات الإعلانية المخطط لها' : 'All planned ad platforms' }}</a></p>
</section>
@endsection
