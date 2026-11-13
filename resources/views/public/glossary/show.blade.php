@php
    $ar = ($lang ?? 'ar') === 'ar';
    $lang = $ar ? 'ar' : 'en';
    $arPath = '/glossary/'.$term;
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $item = \App\Support\Seo\PublicCatalog::glossary()[$term];
    $name = $ar ? $item['term_ar'] : $item['term_en'];
    $heading = \App\Support\Seo\PublicCatalog::glossaryHeading($item, $ar, $term);
    $title = $heading.' | AdsRanger';
    $meta = $ar ? $item['def_ar'] : $item['def_en'];
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $schema = [
        ['@type' => 'DefinedTerm', 'name' => $name, 'description' => $meta, 'url' => $selfUrl, 'inDefinedTermSet' => \App\Support\PublicUrl::absolute('/glossary', $lang)],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'قاموس المصطلحات' : 'Glossary', 'item' => \App\Support\PublicUrl::absolute('/glossary', $lang)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => $selfUrl],
        ]],
    ];
@endphp
@extends('layouts.public')

@section('hero')
<h1>{{ $heading }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? $item['def_ar'] : $item['def_en'] }}</p>
@endsection

@section('content')
<div style="display:grid;gap:18px;padding-top:24px">
    <section class="card" style="padding:20px">
        <h2 style="font-size:19px;margin:0 0 8px">{{ $ar ? 'المعادلة وطريقة الحساب' : 'Formula & calculation' }}</h2>
        <p style="font-weight:600;margin:0" dir="ltr">{{ $ar ? $item['formula_ar'] : $item['formula_en'] }}</p>
    </section>
    <section class="card" style="padding:20px">
        <h2 style="font-size:19px;margin:0 0 8px">{{ $ar ? 'مثال تطبيقي' : 'Practical example' }}</h2>
        <p class="muted" style="margin:0;line-height:1.7">{{ $ar ? $item['example_ar'] : $item['example_en'] }}</p>
    </section>
    <p>
        <a href="{{ $u('/glossary') }}">{{ $ar ? '← كل مصطلحات القاموس' : '← All glossary terms' }}</a>
        @if (\App\Support\PublicSite::linkable($item['related_path']))
        · <a href="{{ $u($item['related_path']) }}">{{ $ar ? 'الأداة أو الصفحة المرتبطة →' : 'Related tool or page →' }}</a>
        @endif
    </p>
</div>
@endsection
