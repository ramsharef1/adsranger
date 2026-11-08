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
$holds = $ar ? [
    ['العربية أولًا.', 'كل شاشة وتقرير ورسالة مكتوبة بالعربية أولًا، مع نسخة إنجليزية.'],
    ['النتيجة الحقيقية.', 'تُحسب كل حملة حسب الهدف الذي صُممت من أجله.'],
    ['بياناتك ملكك.', 'صلاحية قراءة فقط للحسابات الإعلانية، وفصل بيانات كل وكالة.'],
] : [
    ['Arabic first.', 'Every screen, report and email is written in Arabic first, with an English version.'],
    ['The real result.', 'Each campaign is counted by the goal it was built for.'],
    ['Your data stays yours.', 'Read-only access to ad accounts, each agency kept apart.'],
];
@endphp
@extends('layouts.public')

@push('styles')
<style>.ab{max-width:820px;padding-top:24px}.ab a.pl{display:inline-flex;align-items:center;min-height:44px}</style>
@endpush

@section('content')
<section class="ab">
    <h1>{{ $ar ? 'نبنيها في عمّان لمعلني المنطقة' : 'Built in Amman for the region\'s advertisers' }}</h1>
    <p class="muted" style="font-size:17px">{!! $ar
        ? 'تُعدّ <bdi dir="ltr">AdsRanger</bdi> مساحة عمل إعلانية عربية أولًا للوكالات والمستقلين والشركات في الشرق الأوسط وشمال أفريقيا، وهي قيد التطوير، والإطلاق في 2027.'
        : 'AdsRanger is an Arabic-first advertising workspace for agencies, freelancers and businesses across the Middle East and North Africa. It is in development and launching in 2027.' !!}</p>

    <h2>{{ $ar ? 'لماذا نبنيها' : 'Why we\'re building it' }}</h2>
    <p>{{ $ar
        ? 'تدير الفرق في المنطقة إعلاناتها على منصات كثيرة، وترسل التقارير لعملائها بلغتين، وتخطط حول رمضان والتقويم الهجري، بينما لم تُبنَ معظم الأدوات لشيء من ذلك.'
        : 'Teams in the region run ads on many platforms, report to clients in two languages and plan around Ramadan and the Hijri calendar. Most tools were built for none of that.' }}</p>

    <h2>{{ $ar ? 'ما نلتزم به' : 'What we hold to' }}</h2>
    @foreach ($holds as $i => [$head, $body])
        <h3>{{ $head }}</h3>
        <p>{{ $body }}@if ($i === 2) <a class="pl" href="{{ $u('/security') }}">{{ $ar ? 'كيف نحمي بياناتك' : 'How we protect your data' }}</a>@endif</p>
    @endforeach

    <p class="muted" style="margin-top:28px">{{ $ar ? 'تأسست في 2026 · عمّان، الأردن' : 'Founded 2026 · Amman, Jordan' }}</p>
    <p><a class="pl" href="{{ $u('/contact') }}">{{ $ar ? 'تواصل معنا' : 'Contact us' }}</a> · <a class="pl" href="{{ $u('/security') }}">{{ $ar ? 'الأمان' : 'Security' }}</a></p>
    <p><a class="btn" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
</section>
@endsection
