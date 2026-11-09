@php
    $ar = ($lang ?? 'ar') === 'ar';
    $lang = $ar ? 'ar' : 'en';
    $arPath = '/blog';
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $posts = \App\Support\Seo\PublicCatalog::posts();
    $title = $ar ? 'المدونة والأدلة العملية للإعلانات والسيو | AdsRanger' : 'Ad Operations & Arabic SEO Blog | AdsRanger';
    $meta = $ar
        ? 'أدلة عملية وقوائم تحقق لوكالات التسويق وأصحاب المشاريع حول تخطيط حملات رمضان 2027 وتقارير الإعلانات والسيو العربي.'
        : 'Practical guides and checklists for agencies and business owners covering Ramadan 2027 campaigns, budget pacing, and Arabic SEO.';
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $schema = [
        ['@type' => 'CollectionPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'المدونة' : 'Blog', 'item' => $selfUrl],
        ]],
    ];
@endphp
@extends('layouts.public')

@section('hero')
<h1>{{ $ar ? 'المدونة والأدلة العملية' : 'Blog & Practical Guides' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'مقالات تطبيقية وقوائم تحقق لتخطيط المواسم الإعلانية وتقارير العملاء في المنطقة.' : 'Actionable checklists and guides for seasonal campaign planning and client reporting in MENA.' }}</p>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:18px;padding-top:24px">
    @foreach ($posts as $slug => $p)
        <article class="card" style="padding:20px;display:flex;flex-direction:column;justify-content:space-between;gap:12px">
            <div>
                <h2 style="font-size:18px;margin:0 0 8px">
                    <a href="{{ $u('/blog/'.$slug) }}">{{ str_replace(' | AdsRanger', '', $ar ? $p['title_ar'] : $p['title_en']) }}</a>
                </h2>
                <p class="muted" style="margin:0;font-size:14px;line-height:1.65">{{ $ar ? $p['summary_ar'] : $p['summary_en'] }}</p>
            </div>
            <div>
                <a href="{{ $u('/blog/'.$slug) }}" style="font-weight:600">{{ $ar ? 'اقرأ الدليل ←' : 'Read guide →' }}</a>
            </div>
        </article>
    @endforeach
</div>
@endsection
