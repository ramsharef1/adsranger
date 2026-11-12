@php
// /bot (Product slice 0; Business copy 913 · 2026-11-12 16:54, robots / rate / page-cap lines added with site audit A1, E1-049).
// Checked against the code: free tools = one GET per page a person asks for (hreflang: up to 10 pages); the site audit (Crawler) reads
// robots.txt, waits 1 s between requests, stays on the same host, stops at 200 pages. Never private addresses, sign-in or forms; the free tools store nothing.
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/bot';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$title = $ar ? 'زاحف AdsRanger (AdsRangerBot) | AdsRanger' : 'AdsRangerBot: the AdsRanger crawler | AdsRanger';
$meta = $ar
    ? 'ما هو AdsRangerBot، ومتى يفتح صفحات موقعك، وكيف تتعرّف عليه أو تمنعه.'
    : 'What AdsRangerBot is, when it opens pages on your site, and how to recognise or block it.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
$bdi = fn (string $t) => '<bdi dir="ltr">'.$t.'</bdi>';
$ua = \App\Support\Seo\SafeFetcher::USER_AGENT;
$how = $ar ? [
    'في الأدوات المجانية يفتح فقط الصفحة التي يطلب شخص فحصها، وفي فاحص روابط اللغات حتى 10 صفحات مرتبطة بها.',
    'في فحص الموقع الكامل (لعملاء '.$bdi('AdsRanger').' الذين أكدوا إذن صاحب الموقع) يحترم ملف '.$bdi('robots.txt').'، ويرسل طلبًا واحدًا في الثانية على الأكثر، ويبقى على النطاق نفسه، ويتوقف عند 200 صفحة في الفحص الواحد.',
    'طلب '.$bdi('GET').' عادي للصفحة العامة كما يراها الزائر.',
    'لا يتصل بعناوين خاصة أو داخلية.',
    'لا يسجّل الدخول ولا يرسل النماذج.',
] : [
    'In the free tools it opens only the page a person asks to check, and for the hreflang checker up to 10 pages linked from it.',
    'In the full site audit (for AdsRanger customers who confirmed the site owner\'s permission) it follows robots.txt, sends at most 1 request per second, stays on the same host and stops at 200 pages per audit.',
    'A plain GET request for the public page, as a visitor sees it.',
    'It never connects to private or internal addresses.',
    'It never signs in or submits forms.',
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.bot{padding-top:24px;max-width:860px}
.bot pre{direction:ltr;text-align:left;background:var(--surface);padding:12px 16px;border-radius:8px;font-size:14px;white-space:pre-wrap;overflow-wrap:anywhere}
</style>
@endpush

@section('hero')
<h1>{!! $ar ? 'زاحف '.$bdi('AdsRanger').' ('.$bdi('AdsRangerBot').')' : 'AdsRangerBot: the AdsRanger crawler' !!}</h1>
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
        ? 'لمنع فحص الموقع الكامل، أضف إلى ملف '.$bdi('robots.txt').':'
        : 'To block the full site audit, add to your robots.txt:' !!}</p>
    <pre>User-agent: AdsRangerBot
Disallow: /</pre>
    <p>{!! $ar
        ? 'الأدوات المجانية تفتح الصفحة التي يطلبها شخص بعينه كما يفعل المتصفح، فلا تقرأ '.$bdi('robots.txt').'؛ لمنعها احظر اسم '.$bdi('AdsRangerBot').' في إعدادات الخادم أو جدار الحماية، أو راسلنا.'
        : 'The free tools open the page a specific person asks for the way a browser does, so they don\'t read robots.txt; to block them, block the name AdsRangerBot in your server or firewall settings, or email us.' !!}</p>

    <h2>{{ $ar ? 'البيانات' : 'Data' }}</h2>
    <p>{{ $ar ? 'الأدوات المجانية لا تحفظ الروابط أو النتائج.' : 'The free tools don\'t store the URLs or results.' }}</p>

    <p style="margin-top:20px">{!! $ar
        ? 'لأي سؤال أو مشكلة: <a href="mailto:support@adsranger.com"><bdi dir="ltr">support@adsranger.com</bdi></a>'
        : 'Questions or problems: <a href="mailto:support@adsranger.com">support@adsranger.com</a>' !!}</p>
</section>
@endsection
