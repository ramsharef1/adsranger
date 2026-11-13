@php
// Website P-7 "Ad platforms" (U-17, UX-156; ADSR-050 labels; no "connect" before a network is verified).
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/integrations';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$title = $ar ? 'المنصات الإعلانية التي نخطط لربطها | AdsRanger' : 'Ad platforms we plan to connect | AdsRanger';
$meta = $ar
    ? 'المنصات الإعلانية التي تخطط AdsRanger لربطها لوكالات المنطقة، عبر الواجهة الرسمية لكل منصة. قيد التطوير.'
    : 'The ad platforms AdsRanger plans to connect for MENA agencies, through each network\'s official API. In development.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
// CL-1 rev 2 E-04 (Growth 08e §4): one "what AdsRanger will show" line per platform, all still «قيد التطوير».
$first = [
    ['Meta', $ar ? 'الإنفاق والنتائج حسب هدف كل حملة: الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات، على منصتي <bdi dir="ltr">Facebook</bdi>، <bdi dir="ltr">Instagram</bdi>.' : 'Spend and results by each campaign\'s goal — messages, calls, leads, bookings and sales — on Facebook and Instagram.'],
    ['Google Ads', $ar ? 'الإنفاق والتحويلات والمكالمات، مع عبارات البحث التي جلبت النقرات.' : 'Spend, conversions and calls, with the search terms behind your clicks.'],
    ['TikTok', $ar ? 'الإنفاق والنتائج حسب هدف الحملة، مع أداء كل إعلان فيديو.' : 'Spend and results by campaign goal, with each video ad\'s performance.'],
    ['Snapchat', $ar ? 'الإنفاق والنتائج حسب هدف الحملة، مع أداء كل إعلان.' : 'Spend and results by campaign goal, with each ad\'s performance.'],
    ['Microsoft Advertising', $ar ? 'الإنفاق والنتائج لحملات البحث، مع عبارات البحث.' : 'Spend and results for search campaigns, with search terms.'],
    ['LinkedIn', $ar ? 'الإنفاق ونماذج العملاء المحتملين والنتائج حسب هدف الحملة.' : 'Spend, lead forms and results by campaign goal.'],
    ['X', $ar ? 'الإنفاق والنتائج حسب هدف الحملة.' : 'Spend and results by campaign goal.'],
];
$faq = $ar ? [
    ['متى يُفتح كل تكامل؟', 'بعد أن توافق المنصة على تطبيقنا. نعلن كل تكامل عند فتحه، ونراسل قائمة الانتظار.'],
    ['هل يمكنني فصل حساب بعد ربطه؟', 'نعم، في أي وقت، ودون أن يتأثر أي شيء في حسابك الإعلاني.'],
] : [
    ['When does each integration open?', 'After the platform approves our app. We announce each one when it opens and email the waitlist.'],
    ['Can I disconnect an account later?', 'Yes, at any time, and nothing in your ad account changes.'],
];
$schema[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)];
$later = ['Apple Ads', 'Amazon Ads', 'Pinterest', 'Reddit', 'Taboola', 'Outbrain'];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.nets{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(250px,100%),1fr));gap:16px;list-style:none;padding:0;margin:0}
.nets .card-head{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.nets p{margin:8px 0 0;color:var(--muted)}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'المنصات الإعلانية التي نخطط لربطها' : 'The ad platforms we plan to connect' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar
? 'هذه التكاملات قيد التطوير: لا يُفتح أي منها للوكالات بعد، وكل تكامل يُفتح بعد موافقة المنصة على تطبيقنا. نستخدم الواجهات الرسمية لكل منصة، ولا يتغيّر شيء في حساباتك <a href="'.$u('/security').'">دون موافقتك</a>.'
: 'These integrations are in development: none is open to agencies yet, and each one opens after the platform approves our app. We use each platform\'s official API, and nothing in your accounts changes <a href="'.$u('/security').'">without your approval</a>.' !!}</p>
@endsection

@section('content')

<section aria-labelledby="h-first">
    <h2 id="h-first">{{ $ar ? 'المرحلة الأولى' : 'First stage' }}</h2>
    <ul class="nets">
        @foreach ($first as [$name, $line])
            <li class="card">
                <div class="card-head">
                    <h3><bdi dir="ltr">{{ $name }}</bdi></h3>
                    <span class="badge">{{ $ar ? 'قيد التطوير' : 'In development' }}</span>
                </div>
                <p class="muted" style="margin:6px 0 2px;font-size:14px">{!! $ar ? 'ما ستعرضه <bdi dir="ltr">AdsRanger</bdi>' : 'What AdsRanger will show' !!}</p>
                <p>{!! $line !!}</p>
            </li>
        @endforeach
    </ul>
    <p>{!! $ar ? 'شاهد كيف تظهر هذه المنصات معًا في <a href="'.$u('/demo-report').'">تقرير موحد لكل المنصات</a>.' : 'See how these platforms come together in <a href="'.$u('/demo-report').'">one report across platforms</a>.' !!}</p>
</section>

<section aria-labelledby="h-later">
    <h2 id="h-later">{{ $ar ? 'مرحلة لاحقة' : 'Later stage' }}</h2>
    <div class="card" style="background:var(--surface)">
        <p style="margin:0">
            @foreach ($later as $i => $name)<bdi dir="ltr">{{ $name }}</bdi>@if ($i < count($later) - 1){{ $ar ? '، ' : ' · ' }}@endif @endforeach
            <span class="badge badge-neutral">{{ $ar ? 'مخطط له' : 'Planned' }}</span>
        </p>
    </div>
    <p>{!! $ar ? 'تريد منصة غير موجودة هنا؟ <a href="'.$u('/partners').'">اقترح منصة أو انضم كشريك</a>.' : 'Need a platform that isn\'t listed? <a href="'.$u('/partners').'">Suggest a platform or join as a partner</a>.' !!}</p>
</section>

<section aria-labelledby="h-how">
    <h2 id="h-how">{{ $ar ? 'كيف سيتم الربط' : 'How connecting will work' }}</h2>
    <p>{!! $ar
        ? 'ستسجّل الدخول إلى كل منصة إعلانية من صفحتها الرسمية، وتختار الحسابات التي تريد ربطها. تطلب بعض المنصات صلاحية إدارة عند الربط، لكن لا يتغيّر شيء في حساباتك دون موافقة أحد أعضاء فريقك. ويمكنك فصل أي حساب في أي وقت من الإعدادات أو من المنصة نفسها، وعند الفصل يمكنك أن تطلب حذف بياناته كما في <a href="'.$u('/data-deletion').'">صفحة حذف البيانات</a>.'
        : 'You\'ll sign in to each ad platform on its official page and choose the accounts to connect. Some platforms ask for management permission when you connect, but nothing in your accounts changes without a team member\'s approval. You can disconnect any account at any time, from settings or from the platform itself, and when you do, you can ask us to delete its data, as described on the <a href="'.$u('/data-deletion').'">data deletion page</a>.' !!}</p>
</section>

<section aria-labelledby="h-faq">
    <h2 id="h-faq">{{ $ar ? 'أسئلة شائعة' : 'FAQ' }}</h2>
    @foreach ($faq as [$q, $a])
        <h3 style="font-size:17px;margin:16px 0 4px">{{ $q }}</h3>
        <p style="margin:0">{{ $a }}</p>
    @endforeach
</section>
<p style="margin-top:24px"><a class="btn" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a></p>
@endsection
