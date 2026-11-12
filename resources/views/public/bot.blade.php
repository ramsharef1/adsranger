@php
// /bot (Product slice 0; Business copy 913 · 2026-11-12 16:54). Every line checked against SafeFetcher + Hreflang + routes on 2026-11-12:
// one GET per page a person asks for (hreflang: up to 10 pages), no private or internal addresses, no sign-in, no forms, nothing stored by the free tools.
// Business's robots.txt / 1 per second / 200 pages lines are left out: no site crawler exists yet and robots.txt is not read for a single requested page.
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/bot';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$title = $ar ? 'AdsRangerBot: زاحف AdsRanger | AdsRanger' : 'AdsRangerBot: the AdsRanger crawler | AdsRanger';
$meta = $ar
    ? 'ما هو AdsRangerBot، ومتى يفتح صفحات موقعك، وكيف تتعرّف عليه أو تمنعه.'
    : 'What AdsRangerBot is, when it opens pages on your site, and how to recognise or block it.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
$bdi = fn (string $t) => '<bdi dir="ltr">'.$t.'</bdi>';
$ua = \App\Support\Seo\SafeFetcher::USER_AGENT;
$how = $ar ? [
    'يفتح فقط الصفحة التي يطلب شخص فحصها، وفي فاحص روابط اللغات حتى 10 صفحات مرتبطة بها.',
    'طلب '.$bdi('GET').' عادي للصفحة العامة كما يراها الزائر.',
    'لا يتصل بعناوين خاصة أو داخلية.',
    'لا يسجّل الدخول ولا يرسل النماذج.',
] : [
    'It opens only the page a person asks to check, and for the hreflang checker up to 10 pages linked from it.',
    'A plain GET request for the public page, as a visitor sees it.',
    'It never connects to private or internal addresses.',
    'It never signs in or submits forms.',
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.bot{padding-top:24px;max-width:860px}
.bot pre{direction:ltr;text-align:left;background:var(--surface);padding:12px 16px;border-radius:8px;overflow-x:auto;font-size:14px}
</style>
@endpush

@section('hero')
<h1>{!! $ar ? $bdi('AdsRangerBot').': زاحف '.$bdi('AdsRanger') : 'AdsRangerBot: the AdsRanger crawler' !!}</h1>
<p class="muted" style="font-size:17px">{!! $ar
? 'يفتح '.$bdi('AdsRangerBot').' صفحات عامة فقط، عندما يشغّل شخص إحدى أدوات '.$bdi('AdsRanger').' المجانية، مثل فاحص روابط اللغات أو فحص الصفحة.'
: 'AdsRangerBot opens public pages only when someone runs one of AdsRanger\'s free tools, such as the hreflang checker or the page audit.' !!}</p>
@endsection

@section('content')
<section class="bot">
    <h2>{{ $ar ? 'كيف يعمل' : 'How it behaves' }}</h2>
    <ul>
        @foreach ($how as $line)<li>{!! $line !!}</li>@endforeach
    </ul>

    <h2>{{ $ar ? 'كيف تتعرّف عليه' : 'How to recognise it' }}</h2>
    <p>{!! $ar ? 'يرسل هذا الاسم في ترويسة '.$bdi('User-Agent').':' : 'It sends this User-Agent header:' !!}</p>
    <pre>{{ $ua }}</pre>

    <h2>{{ $ar ? 'كيف تمنعه' : 'How to block it' }}</h2>
    <p>{!! $ar
        ? 'لأن كل فحص يطلبه شخص بعينه، يفتح الزاحف الصفحة كما يفعل المتصفح ولا يقرأ ملف '.$bdi('robots.txt').'. لمنعه، احظر اسم '.$bdi('AdsRangerBot').' في إعدادات الخادم أو جدار الحماية لديك، أو راسلنا.'
        : 'Because a specific person asks for each check, it opens the page the way a browser does and doesn\'t read robots.txt. To block it, block the name AdsRangerBot in your server or firewall settings, or email us.' !!}</p>

    <h2>{{ $ar ? 'البيانات' : 'Data' }}</h2>
    <p>{{ $ar ? 'الأدوات المجانية لا تحفظ الروابط أو النتائج.' : 'The free tools don\'t store the URLs or results.' }}</p>

    <p style="margin-top:20px">{!! $ar
        ? 'لأي سؤال أو مشكلة: <a href="mailto:support@adsranger.com"><bdi dir="ltr">support@adsranger.com</bdi></a>'
        : 'Questions or problems: <a href="mailto:support@adsranger.com">support@adsranger.com</a>' !!}</p>
</section>
@endsection
