@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools',
    ['أدوات مجانية للسيو العربي والإعلانات | AdsRanger', 'Free Arabic SEO & Ad Tools | AdsRanger'],
    ['مجموعة أدوات مجانية من AdsRanger لفحص السيو العربي وروابط اللغات وحساب ميزانيات الإعلانات وعائد الإنفاق، فورًا ودون تسجيل.',
        'Free Arabic SEO and ad calculators by AdsRanger: audit Arabic pages, check hreflang, preview SERPs, and plan ad budgets with no sign-up.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$seoSlugs = ['/tools/page-audit', '/tools/hreflang-checker', '/tools/robots-sitemap-checker', '/tools/llms-txt-generator', '/tools/schema-generator', '/tools/arabic-keyword-generator', '/tools/arabizi-converter', '/tools/serp-preview', '/tools/meta-length-checker'];
$adSlugs = ['/tools/ramadan-budget-calculator', '/tools/cod-poas-calculator', '/tools/roas-calculator', '/tools/cpm-cpc-cpa-calculator', '/tools/budget-pacing-calculator', '/tools/agency-fee-vat-calculator', '/tools/ab-significance-calculator', '/tools/capi-pixel-checker', '/tools/whatsapp-link-generator', '/tools/ad-mockup-preview', '/tools/safe-zone-checker', '/tools/ksa-ad-compliance-checker', '/tools/utm-builder'];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
<style>.th-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin:16px 0 32px}.th-card{display:block;text-decoration:none;color:inherit;border:1px solid var(--line,#e2e8f0);border-radius:10px;padding:18px;background:var(--card,#fff);transition:border-color .15s}.th-card:hover{border-color:var(--brand,#0f766e)}.th-card h3{margin:0 0 6px;font-size:18px}.th-card p{margin:0;color:var(--muted,#64748b);font-size:14px}</style>
@endpush
@section('hero')
<h1>{{ $ar ? 'الأدوات المجانية' : 'Free tools' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'أدوات عملية مجانية لفرق التسويق والسيو في المنطقة العربية، تعمل مباشرة في المتصفح دون تسجيل.' : 'Practical, free tools for Arabic SEO and performance marketing teams in MENA. Use them right in your browser with no sign-up.' }}</p>
@endsection
@section('content')
<h2>{{ $ar ? 'أدوات السيو العربي ومحتوى البحث' : 'Arabic SEO & search tools' }}</h2>
<div class="th-grid">
@foreach($seoSlugs as $p)
    <a class="th-card" href="{{ $u($p) }}">
        <h3>{{ \App\Support\PublicSite::name($p, $ar) }}</h3>
        <p>{{ \App\Support\PublicSite::line($p, $ar) }}</p>
    </a>
@endforeach
</div>
<h2>{{ $ar ? 'حاسبات الإعلانات والميزانيات' : 'Ad & budget calculators' }}</h2>
<div class="th-grid">
@foreach($adSlugs as $p)
    <a class="th-card" href="{{ $u($p) }}">
        <h3>{{ \App\Support\PublicSite::name($p, $ar) }}</h3>
        <p>{{ \App\Support\PublicSite::line($p, $ar) }}</p>
    </a>
@endforeach
</div>
@endsection
