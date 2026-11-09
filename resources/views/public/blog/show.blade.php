@php
    $ar = ($lang ?? 'ar') === 'ar';
    $lang = $ar ? 'ar' : 'en';
    $arPath = '/blog/'.$slug;
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $post = \App\Support\Seo\PublicCatalog::posts()[$slug];
    $title = $ar ? $post['title_ar'] : $post['title_en'];
    $meta = $ar ? $post['meta_ar'] : $post['meta_en'];
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
            ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'المدونة' : 'Blog', 'item' => \App\Support\PublicUrl::absolute('/blog', $lang)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $h1, 'item' => $selfUrl],
        ]],
    ];
@endphp
@extends('layouts.public')

@section('hero')
<p class="muted" style="font-size:13px;margin:0 0 8px">{{ $ar ? 'فريق AdsRanger · آخر مراجعة: نوفمبر 2026' : 'AdsRanger team · last checked: November 2026' }}</p>
<h1>{{ $h1 }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? $post['summary_ar'] : $post['summary_en'] }}</p>
@endsection

@section('content')
<div style="display:grid;gap:18px;padding-top:24px">
    @foreach (($ar ? $post['sections_ar'] : $post['sections_en']) as $sec)
        <section class="card" style="padding:20px">
            <h2 style="font-size:19px;margin:0 0 8px">{{ $sec['h'] }}</h2>
            <p class="muted" style="margin:0;line-height:1.75">{{ $sec['p'] }}</p>
        </section>
    @endforeach
    <p>
        <a href="{{ $u('/blog') }}">{{ $ar ? '← كل الأدلة والمقالات' : '← All guides & articles' }}</a>
        @foreach ($post['related_links'] as $rl)
            · <a href="{{ $u($rl) }}">{{ \App\Support\PublicSite::name($rl, $ar) ?: $rl }}</a>
        @endforeach
    </p>
</div>
@endsection
