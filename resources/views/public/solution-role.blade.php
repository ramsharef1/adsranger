@php
    $ar = ($lang ?? 'ar') === 'ar';
    $lang = $ar ? 'ar' : 'en';
    $arPath = '/'.$pageKey;
    $u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
    $item = \App\Support\Seo\PublicCatalog::solutionsAndRoles()[$pageKey][$lang];
    $title = $item['title'];
    $meta = $item['meta'];
    $selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
    $schema = [
        ['@type' => 'WebPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $item['nav'], 'item' => $selfUrl],
        ]],
    ];
@endphp
@extends('layouts.public')

@section('hero')
<p class="muted" style="font-size:13px;font-weight:600;margin:0 0 8px">{{ $ar ? 'قيد التطوير · الإطلاق في 2027' : 'In development · launching in 2027' }}</p>
<h1>{{ $item['h1'] }}</h1>
<p class="muted" style="font-size:17px">{!! $item['lead'] !!}</p>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:18px;padding-top:24px">
    @foreach ($item['cards'] as $card)
        <article class="card" style="padding:20px;display:flex;flex-direction:column;justify-content:space-between;gap:12px">
            <div>
                <h2 style="font-size:19px;margin:0 0 8px">{{ $card['h'] }}</h2>
                <p class="muted" style="margin:0;line-height:1.65">{{ $card['p'] }}</p>
            </div>
            <div>
                <a href="{{ $card['link'] }}" style="font-weight:600">{{ $card['link_label'] }} →</a>
            </div>
        </article>
    @endforeach
</div>
@endsection
