@php
// Website P-23 /about (D-120 company only, no names; D-165 founded 2026; D-216 built now).
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/about';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$title = $ar ? 'عن AdsRanger: تقارير إعلانية عربية أولًا من عمّان' : 'About AdsRanger: Arabic-First Ad Reporting from Amman';
$meta = $ar
    ? 'مساحة عمل إعلانية عربية أولًا لوكالات المنطقة والمستقلين والشركات، تُبنى AdsRanger في عمّان، الأردن. قيد التطوير، والإطلاق في 2027.'
    : 'AdsRanger is an Arabic-first advertising workspace for MENA agencies, freelancers and businesses, built in Amman, Jordan. In development; launching 2027.';
$schema = [
    ['@type' => 'AboutPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $lang), 'inLanguage' => $lang],
    ['@type' => 'Organization', 'name' => 'AdsRanger', 'url' => 'https://adsranger.com/', 'logo' => 'https://adsranger.com/brand/icon-512.png',
        'foundingDate' => '2026', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Amman', 'addressCountry' => 'JO']],
];
// CL-1 rev 2 E-05 (Growth 08e §5).
$holds = $ar ? [
    ['العربية أولًا.', 'كل شاشة وتقرير ورسالة مكتوبة بالعربية أولًا، مع نسخة إنجليزية. والأرقام والتواريخ مكتوبة كما يقرؤها عملاؤك.', null],
    ['النتيجة الحقيقية.', 'تُحسب كل حملة حسب الهدف الذي صُممت من أجله: رسالة أو مكالمة أو عميل محتمل أو بيع.', ['/demo-report', 'شاهد تقريرًا تجريبيًا']],
    ['بياناتك ملكك.', 'لا تغيير في الحسابات الإعلانية دون موافقتكم، وفصل بيانات كل وكالة، ويمكنك حذف بياناتك متى شئت.', ['/security', 'كيف نحمي بياناتك']],
] : [
    ['Arabic first.', 'Every screen, report and message is written in Arabic first, with an English version. Numbers and dates are written the way your clients read them.', null],
    ['Real results.', 'Each campaign is counted by the goal it was built for: a message, a call, a lead or a sale.', ['/demo-report', 'See a sample report']],
    ['Your data is yours.', 'No change to ad accounts without your approval, each agency kept apart, and you can delete your data whenever you like.', ['/security', 'How we protect your data']],
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.ab{max-width:920px;padding-top:24px}
.ab a.pl{display:inline-flex;align-items:center;min-height:44px}
.ab-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr));gap:14px;margin-top:14px}
.ab-grid .card p{margin:6px 0 0;color:var(--muted)}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'نبنيها في عمّان لمعلني المنطقة' : 'Built in Amman for the region\'s advertisers' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar
? 'تُعدّ <bdi dir="ltr">AdsRanger</bdi> مساحة عمل إعلانية عربية أولًا للوكالات والمستقلين والشركات في الشرق الأوسط وشمال أفريقيا، وهي قيد التطوير، والإطلاق في 2027.'
: 'AdsRanger is an Arabic-first advertising workspace for agencies, freelancers and businesses across the Middle East and North Africa. It is in development and launching in 2027.' !!}</p>
@endsection

@section('content')
<section class="ab">

    <h2>{{ $ar ? 'القصة' : 'Our story' }}</h2>
    <p>{!! $ar
        ? 'تدير الفرق في المنطقة إعلاناتها على منصات كثيرة، وترسل التقارير لعملائها بلغتين، وتخطط حول رمضان والتقويم الهجري، بينما لم تُبنَ معظم الأدوات لشيء من ذلك. لذلك بدأنا <bdi dir="ltr">AdsRanger</bdi> في عمّان: مساحة عمل عربية أولًا، يبنيها فريق عمل سنوات في إدارة إعلانات المنطقة. إن كنت تدير وكالة وتريد أن تشكّل المنتج معنا، <a href="'.$u('/partners').'">ساعدنا في البناء كشريك</a>.'
        : 'Teams in the region run ads on many platforms, report to clients in two languages and plan around Ramadan and the Hijri calendar, yet most tools weren\'t built for any of that. So we started AdsRanger in Amman: an Arabic-first workspace, built by a team that has spent years running ads in the region. If you run an agency and want to shape the product with us, <a href="'.$u('/partners').'">help us build it as a partner</a>.' !!}</p>

    <h2>{{ $ar ? 'ما نلتزم به' : 'What we hold to' }}</h2>
    <div class="ab-grid">
        @foreach ($holds as [$head, $body, $link])
            <div class="card">
                <h3>{{ $head }}</h3>
                <p>{{ $body }}@if ($link) <a class="pl" href="{{ $u($link[0]) }}">{{ $link[1] }}</a>@endif</p>
            </div>
        @endforeach
    </div>

    <h2 style="margin-top:28px">{{ $ar ? 'أين نحن الآن' : 'Where we are now' }}</h2>
    <p>{!! $ar
        ? 'تأسست <bdi dir="ltr">AdsRanger</bdi> في 2026، وما زالت قيد التطوير، والإطلاق في 2027. نعمل الآن مع عدد قليل من وكالات المنطقة على شكل المنتج قبل الإطلاق.'
        : 'AdsRanger was founded in 2026. It\'s in development and launching in 2027. We\'re now working with a small group of agencies in the region on the product before launch.' !!}</p>

    <p class="muted" style="margin-top:28px">{{ $ar ? 'تأسست في 2026 · عمّان، الأردن' : 'Founded 2026 · Amman, Jordan' }}</p>
    <p><a class="pl" href="{{ $u('/contact') }}">{{ $ar ? 'تواصل معنا' : 'Contact us' }}</a> · <a class="pl" href="{{ $u('/security') }}">{{ $ar ? 'الأمان' : 'Security' }}</a></p>
    <p><a class="btn" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
</section>
@endsection
