@php
// Business-owners hub /solutions/business-owners (P-13 in-house copy, B-08; D-202, D-209, D-213). Agency guard: never
// linked from agency client surfaces; owner voice; no prices; guides list fills as each P7 guide publishes.
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/solutions/business-owners';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$title = $ar ? 'أدِر إعلانات مشروعك بنفسك | AdsRanger' : 'Run Your Own Business\'s Ads | AdsRanger';
$meta = $ar
    ? 'لأصحاب المشاريع الذين يديرون إعلاناتهم بأنفسهم: الإنفاق والنتائج حسب الهدف على منصاتك الإعلانية، بالعربية، مع تنبيهات يومية. قيد التطوير، والإطلاق في 2027.'
    : 'For business owners who run their own ads: spend and results by goal across your ad platforms, in Arabic, with daily alerts. In development; launching 2027.';
$selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
$schema = [
    ['@type' => 'WebPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'لأصحاب المشاريع' : 'For business owners', 'item' => $selfUrl],
    ]],
];
$features = $ar
    ? ['النتائج حسب الهدف', 'تنبيهات يومية', 'متابعة وتيرة الإنفاق', 'مخطِّط رمضان، في الباقة الاحترافية']
    : ['Results by goal', 'Daily alerts', 'Spend pacing', 'Ramadan planner, on the Pro plan'];
// Only live tools and published guides (B-08: no "coming soon"); guides are added here on their publish day.
$tools = [[$u('/tools/ramadan-budget-calculator'), $ar ? 'حاسبة ميزانية إعلانات رمضان 2027' : 'Ramadan 2027 ad budget calculator']];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.bo{max-width:920px;padding-top:24px}
.bo ul.dots{padding-inline-start:22px;margin:8px 0 0}
.bo a.pl{display:inline-flex;align-items:center;min-height:44px}
.bo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr));gap:14px;margin-top:18px}
.bo-grid .card h2{margin-top:0}
</style>
@endpush

@section('content')
<section class="bo">
    <h1>{{ $ar ? 'أدِر إعلانات مشروعك بنفسك' : 'Run your own business\'s ads' }}</h1>
    <p class="muted" style="font-size:17px">{{ $ar ? 'إعلاناتك على المنصات الإعلانية التي تستخدمها، في عرض عربي واحد.' : 'Your own ads, on the ad platforms you use, in one Arabic view.' }}</p>

    <ul class="dots">
        <li>{{ $ar ? 'كل منصة تحسب النتائج بطريقتها.' : 'Each platform reports results its own way.' }}</li>
        <li>{{ $ar ? 'لا وقت لمراجعة كل حساب يوميًا.' : 'No time to check every account each day.' }}</li>
    </ul>

    <div class="bo-grid">
        <div class="card">
            <h2>{{ $ar ? 'ما نبنيه لك' : 'What we\'re building for you' }} <span class="badge">{{ $ar ? 'قيد التطوير' : 'In development' }}</span></h2>
            <ul class="dots">@foreach ($features as $f)<li>{{ $f }}</li>@endforeach</ul>
        </div>

        <div class="card">
            <h2>{{ $ar ? 'أدوات وأدلة مجانية' : 'Free tools and guides' }}</h2>
            <ul class="dots">@foreach ($tools as [$href, $label])<li><a class="pl" href="{{ $href }}">{{ $label }}</a></li>@endforeach</ul>

            <h2 style="margin-top:20px">{{ $ar ? 'الباقة' : 'Plan' }}</h2>
            <p style="margin:6px 0 0">{{ $ar ? 'باقة المستقل: للمستقلين والشركات التي تدير إعلاناتها بنفسها' : 'Solo: for freelancers and businesses that run their own ads' }} <span class="badge badge-neutral">{{ $ar ? 'مخطط له' : 'Planned' }}</span></p>
        </div>
    </div>

    <p style="margin-top:24px"><a class="btn" href="{{ $u('/waitlist') }}?type=business">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
</section>
@endsection
