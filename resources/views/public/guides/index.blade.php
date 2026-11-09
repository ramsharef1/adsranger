@php
    $ar = ($lang ?? 'ar') === 'ar';
    $lang = $ar ? 'ar' : 'en';
    $arPath = '/guides';
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $guides = \App\Support\Seo\PublicCatalog::guides();
    $title = $ar ? 'الأدلة الشاملة لإدارة الإعلانات والسيو للوكالات | AdsRanger' : 'Agency Pillar Guides for MENA Advertising & SEO | AdsRanger';
    $meta = $ar
        ? 'أدلة مرجعية لوكالات التسويق في المنطقة حول إعلانات Meta وجوجل وسناب شات وتيك توك، وتقارير العملاء، ووتيرة الإنفاق.'
        : 'In-depth pillar guides for MENA marketing agencies covering Meta Ads, Google Ads, Snapchat & TikTok, client reporting, and budget pacing.';
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $schema = [
        ['@type' => 'CollectionPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'الأدلة الشاملة' : 'Guides', 'item' => $selfUrl],
        ]],
    ];
@endphp
@extends('layouts.public')

@section('hero')
<h1>{{ $ar ? 'الأدلة الشاملة لوكالات الإعلان في المنطقة' : 'Pillar Guides for MENA Ad Agencies' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'أدلة مرجعية تغطي إدارة المنصات الإعلانية الرئيسية، إعداد تقارير العملاء، وضبط وتيرة الميزانيات.' : 'Evergreen reference guides covering major ad platforms, client reporting standards, and budget pacing.' }}</p>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:18px;padding-top:24px">
    @foreach ($guides as $slug => $g)
        <article class="card" style="padding:20px;display:flex;flex-direction:column;justify-content:space-between;gap:12px">
            <div>
                <h2 style="font-size:18px;margin:0 0 8px">
                    <a href="{{ $u('/guides/'.$slug) }}">{{ str_replace(' | AdsRanger', '', $ar ? $g['title_ar'] : $g['title_en']) }}</a>
                </h2>
                <p class="muted" style="margin:0;font-size:14px;line-height:1.65">{{ $ar ? $g['summary_ar'] : $g['summary_en'] }}</p>
            </div>
            <div>
                <a href="{{ $u('/guides/'.$slug) }}" style="font-weight:600">{{ $ar ? 'اقرأ الدليل الشامل ←' : 'Read pillar guide →' }}</a>
            </div>
        </article>
    @endforeach
</div>
@endsection
