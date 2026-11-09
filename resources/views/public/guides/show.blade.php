@php
    $ar = ($lang ?? 'ar') === 'ar';
    $lang = $ar ? 'ar' : 'en';
    $arPath = '/guides/'.$slug;
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $g = \App\Support\Seo\PublicCatalog::guides()[$slug];
    $title = $ar ? $g['title_ar'] : $g['title_en'];
    $meta = $ar ? $g['meta_ar'] : $g['meta_en'];
    $h1 = str_replace(' | AdsRanger', '', $title);
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $schema = [
        [
            '@type' => 'Article',
            'headline' => $h1,
            'description' => $meta,
            'url' => $selfUrl,
            'inLanguage' => $lang,
            'author' => ['@type' => 'Organization', 'name' => 'AdsRanger'],
            'publisher' => ['@type' => 'Organization', 'name' => 'AdsRanger'],
            'datePublished' => '2026-11-09',
            'dateModified' => '2026-11-09',
        ],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'الأدلة الشاملة' : 'Guides', 'item' => \App\Support\PublicUrl::absolute('/guides', $lang)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $h1, 'item' => $selfUrl],
        ]],
    ];
@endphp
@extends('layouts.public')

@section('hero')
<p class="muted" style="font-size:13px;margin:0 0 8px">{{ $ar ? 'فريق AdsRanger · دليل مرجعي للوكالات' : 'AdsRanger team · agency reference guide' }}</p>
<h1>{{ $h1 }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? $g['summary_ar'] : $g['summary_en'] }}</p>
@endsection

@section('content')
<div style="display:grid;gap:18px;padding-top:24px">
    @foreach (($ar ? $g['sections_ar'] : $g['sections_en']) as $sec)
        <section class="card" style="padding:20px">
            <h2 style="font-size:19px;margin:0 0 8px">{{ $sec['h'] }}</h2>
            <p class="muted" style="margin:0;line-height:1.75">{{ $sec['p'] }}</p>
        </section>
    @endforeach
    <p>
        <a href="{{ $u('/guides') }}">{{ $ar ? '← كل الأدلة الشاملة' : '← All pillar guides' }}</a>
        · <a href="{{ $u($g['product_link']) }}">{{ $ar ? 'صفحة المنتج المرتبطة →' : 'Related product page →' }}</a>
        @foreach ($g['related_links'] as $rl)
            · <a href="{{ $u($rl) }}">{{ \App\Support\PublicSite::name($rl, $ar) ?: $rl }}</a>
        @endforeach
    </p>
</div>
@endsection
