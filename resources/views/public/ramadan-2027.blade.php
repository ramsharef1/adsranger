@php
// Website P-3 Ramadan 2027 pillar rev 3 (D-150, R-02, R-18, UX-18…23). The calculator moved to P-4.
$ar = ($lang ?? 'ar') === 'ar';
$lang = $ar ? 'ar' : 'en';
$arPath = '/ramadan-2027';
$title = $ar ? 'دليل إعلانات رمضان 2027 لوكالات الإعلان | AdsRanger' : 'Ramadan 2027 Advertising Guide for Agencies | AdsRanger';
$meta = $ar
    ? 'يُتوقع أن يبدأ رمضان 2027 في 8 فبراير. خطة مرحلة بمرحلة لوكالات الإعلان: مواعيد التحضير، والميزانية، والتصاميم الإعلانية، والتقارير عبر المنصات.'
    : 'Ramadan 2027 is expected to start on 8 February. A phase-by-phase plan for agencies: prep dates, budget pacing, creative, and reporting across networks.';
$h1 = $ar ? 'رمضان 2027: خطة الإعلانات لوكالات الإعلان' : 'Ramadan 2027: the advertising plan for agencies';
$ogType = 'article';
$body = view('public.partials.ramadan-2027-'.$lang)->render();
preg_match_all('#<h3>(.*?)</h3>\s*<p>(.*?)</p>#s', $body, $faq, PREG_SET_ORDER);
$selfUrl = \App\Support\PublicUrl::absolute($arPath, $lang);
$schema = [
    ['@type' => 'Article', 'headline' => $h1, 'inLanguage' => $lang, 'url' => $selfUrl,
        'author' => ['@type' => 'Organization', 'name' => 'AdsRanger', 'url' => 'https://adsranger.com/'],
        'datePublished' => '2026-11-07', 'dateModified' => date('Y-m-d', (int) filemtime(resource_path('views/public/partials/ramadan-2027-'.$lang.'.blade.php')))],
    ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($m) => ['@type' => 'Question', 'name' => trim(strip_tags($m[1])),
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($m[2]))]], $faq)],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => \App\Support\PublicUrl::absolute('/', $lang)],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $ar ? 'رمضان 2027' : 'Ramadan 2027', 'item' => $selfUrl],
    ]],
];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.rm{max-width:820px;padding-top:24px}
.rm ul{padding-inline-start:22px}
.rm li{margin:6px 0}
.rm-table{overflow-x:auto;margin:12px 0}
.rm-table table{width:100%;border-collapse:collapse;min-width:520px}
.rm-table th,.rm-table td{padding:10px;border-bottom:1px solid var(--line);text-align:start;vertical-align:top}
.rm-table th{color:var(--muted);font-weight:600}
.rm-table td:first-child,.rm-table th:first-child{position:sticky;inset-inline-start:0;background:var(--bg);font-weight:600}
.rm h3{margin-top:20px}
</style>
@endpush

@section('content')
<article class="rm">
    <h1>{{ $h1 }}</h1>
    {!! $body !!}
</article>
@endsection
