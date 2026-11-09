@php
    $ar = ($lang ?? 'ar') === 'ar';
    $lang = $ar ? 'ar' : 'en';
    $arPath = '/integrations/'.$platform;
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $item = \App\Support\Seo\PublicCatalog::platforms()[$platform];
    $name = $ar ? $item['name_ar'] : $item['name_en'];
    $title = $ar ? "تقارير إعلانات {$item['name_en']} للوكالات | AdsRanger" : "{$item['name_en']} Reporting for Agencies | AdsRanger";
    $meta = $ar
        ? "تعرّف على التكامل المخطط له لمنصة {$item['name_en']} في AdsRanger لمتابعة الإنفاق والنتائج وتيرة الميزانية لعملاء الوكالات."
        : "Learn about the planned {$item['name_en']} integration in AdsRanger for agency spend pacing, conversion tracking, and unified client reports.";
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $schema = [
        ['@type' => 'WebPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'المنصات الإعلانية' : 'Ad platforms', 'item' => \App\Support\PublicUrl::absolute('/integrations', $lang)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => $selfUrl],
        ]],
    ];
@endphp
@extends('layouts.public')

@section('hero')
<p class="muted" style="font-size:13px;font-weight:600;margin:0 0 8px">{{ $ar ? 'تكامل مخطط له · المرحلة الأولى' : 'Planned integration · Stage 1' }}</p>
<h1>{{ $ar ? 'تقارير إعلانات ' : '' }}<bdi dir="ltr">{{ $item['name_en'] }}</bdi>{{ $ar ? ' للوكالات' : ' reporting for agencies' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar ? $item['summary_ar'] : $item['summary_en'] !!}</p>
@endsection

@section('content')
<section class="card" style="margin-top:24px;padding:22px">
    <h2 style="font-size:20px;margin-top:0">{{ $ar ? 'ماذا ستعرض AdsRanger عند ربط المنصة؟' : 'What AdsRanger will report' }}</h2>
    <ul style="margin:12px 0 0;padding-inline-start:20px;line-height:1.75">
        @foreach (($ar ? $item['metrics_ar'] : $item['metrics_en']) as $m)
            <li style="margin-bottom:6px">{{ $m }}</li>
        @endforeach
    </ul>
    <p class="muted" style="margin-top:14px">{{ $ar ? 'لا يتغير أي شيء في حسابك الإعلاني دون موافقة فريقك.' : 'Nothing changes in your ad account without your team\'s approval.' }}</p>
</section>
<p style="margin-top:18px">
    <a href="{{ $u('/integrations') }}">{{ $ar ? '← العودة إلى كل المنصات الإعلانية' : '← Back to all ad platforms' }}</a>
    · <a href="{{ $u($item['guide_link']) }}">{{ $ar ? 'استعرض الدليل المرتبط' : 'Explore related resource' }}</a>
</p>
@endsection
