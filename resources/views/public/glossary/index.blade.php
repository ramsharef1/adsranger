@php
    $ar = ($lang ?? 'ar') === 'ar';
    $lang = $ar ? 'ar' : 'en';
    $arPath = '/glossary';
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $terms = \App\Support\Seo\PublicCatalog::glossary();
    $title = $ar ? 'قاموس مصطلحات الإعلانات الرقمية والسيو | AdsRanger' : 'Digital Advertising & SEO Glossary | AdsRanger';
    $meta = $ar
        ? 'تعريفات واضحة ومعادلات وأمثلة عملية من أسواق المنطقة لأهم مصطلحات الإعلانات الممولة والسيو العربي.'
        : 'Clear definitions, formulas, and practical MENA examples for essential performance marketing and Arabic SEO terms.';
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $schema = [
        ['@type' => 'DefinedTermSet', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'قاموس المصطلحات' : 'Glossary', 'item' => $selfUrl],
        ]],
    ];
@endphp
@extends('layouts.public')

@section('hero')
<h1>{{ $ar ? 'قاموس مصطلحات الإعلانات والسيو' : 'Advertising & SEO Glossary' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'تعريفات مختصرة ومعادلات عملية للمصطلحات التي تستخدمها وكالات التسويق في تقاريرها اليومية.' : 'Concise definitions, formulas, and USD examples for the metrics agencies use in daily reporting.' }}</p>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:16px;padding-top:24px">
    @foreach ($terms as $slug => $t)
        <article class="card" style="padding:18px;display:flex;flex-direction:column;justify-content:space-between;gap:10px">
            <div>
                <h2 style="font-size:18px;margin:0 0 6px">
                    <a href="{{ $u('/glossary/'.$slug) }}">{{ $ar ? $t['term_ar'] : $t['term_en'] }}</a>
                </h2>
                <p class="muted" style="margin:0;font-size:14px;line-height:1.6">{{ $ar ? $t['def_ar'] : $t['def_en'] }}</p>
            </div>
            <div>
                <a href="{{ $u('/glossary/'.$slug) }}" style="font-size:14px;font-weight:600">{{ $ar ? 'اقرأ المعادلة والمثال →' : 'Read formula & example →' }}</a>
            </div>
        </article>
    @endforeach
</div>
@endsection
