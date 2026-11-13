@php
// BX-01 (B-43 / B-44): open datasets on our own domain, with licence, version, changelog, how to cite and Dataset schema.
// Copy: E1 draft from the published README (Growth reviews, O-438). Files are the exact published release.
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/data';
$lng = $ar ? 'ar' : 'en';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lng);
$abs = fn (string $p) => \App\Support\PublicUrl::absolute($p, $lng);
$b = fn (string $t) => '<bdi dir="ltr">'.e($t).'</bdi>';
$sets = config('datasets', []);
$title = $ar ? 'بيانات مفتوحة لتسويق المنطقة | AdsRanger' : 'Open data for MENA marketing | AdsRanger';
$meta = $ar
    ? 'بيانات مفتوحة مجانية من AdsRanger بترخيص CC BY 4.0: تقويم المواسم الإعلانية 2027 بصيغة CSV أو JSON، مع المصادر وطريقة الاستشهاد.'
    : 'Free open data from AdsRanger under CC BY 4.0: the MENA ad calendar 2027 as CSV and JSON, with sources and how to cite it.';
$root = \App\Support\PublicUrl::HOST;
$schema = [['@type' => 'DataCatalog', 'name' => $ar ? 'بيانات AdsRanger المفتوحة' : 'AdsRanger open data', 'url' => $abs('/data'),
    'publisher' => ['@type' => 'Organization', 'name' => 'AdsRanger', 'url' => $root],
    'dataset' => collect($sets)->map(fn ($d, $slug) => [
        '@type' => 'Dataset', 'name' => $d['title_en'], 'alternateName' => $d['title_ar'], 'description' => $d['about_en'],
        'url' => $abs('/data').'#'.$slug, 'version' => $d['version'], 'datePublished' => $d['released'], 'dateModified' => end($d['changelog'])[0],
        'license' => $d['license']['url'], 'isAccessibleForFree' => true, 'inLanguage' => ['ar', 'en'],
        'creator' => ['@type' => 'Organization', 'name' => 'AdsRanger', 'url' => $root],
        'temporalCoverage' => $d['coverage']['from'].'/'.$d['coverage']['to'], 'spatialCoverage' => $d['coverage']['place'],
        'sameAs' => $d['mirror'], 'isBasedOn' => $abs($d['source_path']),
        'distribution' => collect($d['files'])->filter(fn ($f) => in_array($f[0], ['csv', 'json'], true))->map(fn ($f) => ['@type' => 'DataDownload', 'encodingFormat' => $f[2], 'contentUrl' => $root.$f[1]])->values()->all(),
    ])->values()->all()]];
@endphp
@extends('layouts.public')

@section('hero')
<h1>{{ $ar ? 'بيانات مفتوحة' : 'Open data' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar
    ? 'بيانات نجمعها ونراجع مصادرها، مجانية للاستخدام والنشر بترخيص '.$b('CC BY 4.0').'، بشرط ذكر المصدر.'
    : 'Data we collect and source-check, free to use and publish under CC BY 4.0, with credit.' !!}</p>
@endsection

@section('content')
<div style="display:grid;gap:18px;padding-top:24px;max-width:900px">
@foreach ($sets as $slug => $d)
    <article class="card" id="{{ $slug }}" style="padding:20px">
        <h2 style="font-size:21px;margin:0 0 6px">{{ $ar ? $d['title_ar'] : $d['title_en'] }}</h2>
        <p style="margin:0 0 10px;line-height:1.7">{{ $ar ? $d['about_ar'] : $d['about_en'] }}</p>
        <p class="muted" style="margin:0 0 12px">{!! $ar
            ? 'الإصدار '.$b($d['version']).' · '.$b($d['rows']).' موعدًا · '.$b($d['released']).' · الترخيص '.$b($d['license']['name'])
            : 'Version '.$b($d['version']).' · '.$b($d['rows']).' dates · '.$b($d['released']).' · Licence '.$b($d['license']['name']) !!}</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach ($d['files'] as [$kind, $path, $type, $label])
                <a class="btn {{ $kind === 'csv' ? '' : 'btn-ghost' }}" href="{{ $path }}" download style="min-height:44px"><bdi dir="ltr">{{ $label }}</bdi></a>
            @endforeach
        </div>
        <ul style="margin:14px 0 0;line-height:1.8">
            <li>{!! $ar ? 'نفس بيانات <a href="'.e($u($d['source_path'])).'">تقويم المواسم</a> على موقعنا، والموقع دائمًا هو الأحدث.' : 'The same data as our <a href="'.e($u($d['source_path'])).'">seasons calendar</a>; the website is always the latest version.' !!}</li>
            <li>{{ $ar ? 'التواريخ الهجرية متوقعة، وتعتمد على رؤية الهلال والإعلانات الرسمية في كل دولة. راجع المصدر قبل حجز المساحات الإعلانية.' : 'Hijri dates are expected and depend on each country\'s moon sighting and official announcements. Check the source before you book media.' }}</li>
            <li>{!! $ar ? 'نسخة مطابقة على '.$b('GitHub').': <a href="'.e($d['mirror']).'" rel="noopener"><bdi dir="ltr">'.e(parse_url($d['mirror'], PHP_URL_PATH)).'</bdi></a>' : 'Mirror on GitHub: <a href="'.e($d['mirror']).'" rel="noopener">'.e(parse_url($d['mirror'], PHP_URL_PATH)).'</a>' !!}</li>
        </ul>
        <h3 style="font-size:16px;margin:16px 0 6px">{{ $ar ? 'سجل التغييرات' : 'Changelog' }}</h3>
        <ul style="margin:0">
            @foreach (array_reverse($d['changelog']) as [$date, $ver, $noteAr, $noteEn])
                <li>{!! $b($date) !!} · {!! $b($ver) !!} · {{ $ar ? $noteAr : $noteEn }}</li>
            @endforeach
        </ul>
        @include('public.partials.cite', ['citeTitle' => $ar ? $d['title_ar'] : $d['title_en'], 'citeUrl' => $abs('/data').'#'.$slug, 'citeLicense' => $d['license']['name']])
    </article>
@endforeach
</div>
@endsection
