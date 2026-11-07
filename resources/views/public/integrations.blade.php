@php
// Website P-7 "Ad platforms" (U-17, UX-156; ADSR-050 labels; no "connect" before a network is verified).
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/integrations';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$title = $ar ? 'المنصات الإعلانية التي نخطط لربطها | AdsRanger' : 'Ad platforms we plan to connect | AdsRanger';
$meta = $ar
    ? 'المنصات الإعلانية التي يخطط AdsRanger لربطها لوكالات المنطقة، بصلاحية قراءة فقط عبر الواجهة الرسمية لكل منصة. قيد التطوير.'
    : 'The ad platforms AdsRanger plans to connect for MENA agencies, with read-only access through each network\'s official API. In development.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
$tiktokLine = $ar ? 'الإنفاق والنتائج حسب هدف الحملة' : 'Spend and results by campaign goal';
$first = [
    ['Meta', $ar ? 'على <bdi dir="ltr">Facebook</bdi>، <bdi dir="ltr">Instagram</bdi>' : 'on Facebook, Instagram',
        $ar ? 'الرسائل والمكالمات والعملاء المحتملون والحجوزات والمبيعات حسب هدف الحملة' : 'Messages, calls, leads, bookings and sales by campaign goal'],
    ['Google Ads', null, $ar ? 'عبارات البحث والمكالمات والتحويلات' : 'Search terms, calls and conversions'],
    ['TikTok', null, $tiktokLine],
    ['Snapchat', null, $tiktokLine],
    ['Microsoft Advertising', null, $ar ? 'حملات البحث' : 'Search campaigns'],
    ['LinkedIn', null, $ar ? 'نماذج العملاء المحتملين والإنفاق والنتائج' : 'Lead forms, spend and results'],
    ['X', null, $ar ? 'الإنفاق والنتائج' : 'Spend and results'],
];
$later = ['Apple Ads', 'Amazon Ads', 'Pinterest', 'Reddit', 'Taboola', 'Outbrain'];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.nets{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:14px;list-style:none;padding:0;margin:0}
.nets p{margin:8px 0 0}
</style>
@endpush

@section('content')
<section style="padding-top:24px;max-width:820px">
    <h1>{{ $ar ? 'المنصات الإعلانية التي نخطط لربطها' : 'The ad platforms we plan to connect' }}</h1>
    <p class="muted" style="font-size:17px">{{ $ar
        ? 'هذه التكاملات قيد التطوير: لا يُفتح أي منها للوكالات بعد، وكل تكامل يُفتح بعد موافقة المنصة على تطبيقنا. نستخدم الواجهات الرسمية بصلاحية قراءة فقط.'
        : 'These integrations are in development: none is open to agencies yet, and each one opens after the platform approves our app. We use each platform\'s official API with read-only access.' }}</p>
</section>

<section aria-labelledby="h-first">
    <h2 id="h-first">{{ $ar ? 'المرحلة الأولى' : 'First stage' }}</h2>
    <ul class="nets">
        @foreach ($first as [$name, $sub, $line])
            <li class="card">
                <h3><bdi dir="ltr">{{ $name }}</bdi></h3>
                @if ($sub)<p class="muted" style="margin:0 0 6px">{!! $sub !!}</p>@endif
                <span class="badge">{{ $ar ? 'قيد التطوير' : 'In development' }}</span>
                <p>{{ $line }}</p>
            </li>
        @endforeach
    </ul>
</section>

<section aria-labelledby="h-later">
    <h2 id="h-later">{{ $ar ? 'مرحلة لاحقة' : 'Later stage' }}</h2>
    <p>
        @foreach ($later as $i => $name)<bdi dir="ltr">{{ $name }}</bdi>@if ($i < count($later) - 1){{ $ar ? '، ' : ' · ' }}@endif @endforeach
        <span class="badge badge-neutral">{{ $ar ? 'مخطط له' : 'Planned' }}</span>
    </p>
</section>
<p style="margin-top:24px"><a class="btn" href="{{ $u('/waitlist') }}">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@endsection
