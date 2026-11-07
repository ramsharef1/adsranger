@php
    // Legal pages on the shared public layout (Website P-5 §9; text unchanged, D-137).
    $ar = $lang === 'ar';
    $arPath = '/'.$page;
    $titles = [
        'privacy' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
        'terms' => ['ar' => 'شروط الاستخدام', 'en' => 'Terms of Service'],
        'data-deletion' => ['ar' => 'حذف البيانات', 'en' => 'Data Deletion'],
    ];
    $metas = [
        'privacy' => ['ar' => 'كيف تجمع AdsRanger البيانات وتستخدمها وتحميها، وما حقوقك عليها.', 'en' => 'How AdsRanger collects, uses and protects data, and your rights over it.'],
        'terms' => ['ar' => 'شروط استخدام AdsRanger، مساحة العمل الإعلانية لوكالات المنطقة، وهي قيد التطوير.', 'en' => 'The terms for using AdsRanger, the ad workspace for MENA agencies, now in development.'],
        'data-deletion' => ['ar' => 'كيف تطلب حذف بياناتك من AdsRanger، وماذا يُحذف ومتى.', 'en' => 'How to ask AdsRanger to delete your data, what is deleted and when.'],
    ];
    $heading = $titles[$page][$lang];
    $title = $heading.' | AdsRanger';
    $meta = $metas[$page][$lang];
    $schema = [['@type' => 'WebPage', 'name' => $heading, 'url' => \App\Support\PublicUrl::absolute($arPath, $lang), 'inLanguage' => $lang]];
    $updated = $ar ? 'آخر تحديث: 4 نوفمبر 2026' : 'Last updated: 4 November 2026';
@endphp
@extends('layouts.public')

@push('styles')
<style>
.legal{max-width:820px;padding-top:24px}
.legal .updated{color:var(--muted);font-size:14px;margin:0 0 28px}
.legal h2{font-size:20px;margin:30px 0 8px}
.legal ul,.legal ol{padding-inline-start:22px}
.legal li{margin:4px 0}
.legal a[href^="mailto:"]{display:inline-flex;align-items:center;min-height:44px}
.legal .note{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin:18px 0}
</style>
@endpush

@section('content')
<article class="legal">
    <h1>{{ $heading }}</h1>
    <p class="updated">{{ $updated }}</p>
    @include('legal.'.$page.'-'.$lang)
</article>
@endsection
