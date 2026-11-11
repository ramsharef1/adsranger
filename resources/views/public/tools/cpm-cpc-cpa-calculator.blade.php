@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/cpm-cpc-cpa-calculator',
    ['حاسبة مؤشرات الإعلانات الشاملة (CPM, CPC, CTR, CPA) | AdsRanger', 'Full-Funnel CPM, CPC, CTR, CVR & CPA Calculator | AdsRanger'],
    ['احسب وحوّل فورًا بين الميزانية ومرات الظهور وتكلفة الألف ظهور (CPM) ونسبة النقر (CTR) وتكلفة النقرة (CPC) وتكلفة الاكتساب (CPA).',
        'Instant full-funnel media math converter across Budget, Impressions, CPM, CTR, Clicks, CPC, CVR, Conversions, and CPA.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$curs = ['USD', 'SAR', 'AED', 'JOD', 'KWD', 'QAR', 'EGP'];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{!! $ar ? 'حاسبة مؤشرات الإعلانات الشاملة (<bdi dir="ltr">CPM · CPC · CPA</bdi>)' : 'Full-Funnel CPM, CPC & CPA Calculator' !!}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'أدخل أرقام الحملة الأساسية لتحسب كل مؤشرات القمع الإعلاني من الظهور حتى تكلفة العميل.' : 'Convert instantly across spend, impressions, CPM, CTR, clicks, CPC, CVR, conversions, and CPA.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" onsubmit="return false" novalidate>
        <label for="mm-cur">{{ $ar ? 'العملة' : 'Currency' }}</label>
        <select id="mm-cur">@foreach ($curs as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>

        <label for="mm-spend">{{ $ar ? 'الإنفاق الإعلاني (Budget / Spend)' : 'Ad spend' }}</label>
        <input id="mm-spend" type="text" inputmode="decimal" dir="ltr" value="2500">

        <label for="mm-imp">{{ $ar ? 'مرات الظهور (Impressions)' : 'Impressions' }}</label>
        <input id="mm-imp" type="text" inputmode="numeric" dir="ltr" value="400000">

        <label for="mm-clk">{{ $ar ? 'النقرات (Clicks)' : 'Clicks' }}</label>
        <input id="mm-clk" type="text" inputmode="numeric" dir="ltr" value="6000">

        <label for="mm-conv">{{ $ar ? 'التحويلات / العملاء المحتملون (Conversions)' : 'Conversions' }}</label>
        <input id="mm-conv" type="text" inputmode="numeric" dir="ltr" value="180">
    </form>

    <div class="tl-out" aria-live="polite">
        <ul class="tl-kv">
            <li><span><bdi dir="ltr">CPM</bdi> ({{ $ar ? 'تكلفة الألف ظهور' : 'Cost per 1,000 impressions' }})</span><strong id="mm-cpm" dir="ltr"></strong></li>
            <li><span><bdi dir="ltr">CTR</bdi> ({{ $ar ? 'نسبة النقر إلى الظهور' : 'Click-through rate' }})</span><strong id="mm-ctr" dir="ltr"></strong></li>
            <li><span><bdi dir="ltr">CPC</bdi> ({{ $ar ? 'تكلفة النقرة' : 'Cost per click' }})</span><strong id="mm-cpc" dir="ltr"></strong></li>
            <li><span><bdi dir="ltr">CVR</bdi> ({{ $ar ? 'معدل تحويل النقرات' : 'Conversion rate' }})</span><strong id="mm-cvr" dir="ltr"></strong></li>
            <li><span><bdi dir="ltr">CPA / CPL</bdi> ({{ $ar ? 'تكلفة الاكتساب / التحويل' : 'Cost per acquisition' }})</span><strong id="mm-cpa" dir="ltr"></strong></li>
        </ul>
        <p class="muted" style="margin-top:14px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'cpm-cpc-cpa-calculator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); };
  function v(id){ var n = parseFloat($(id).value.replace(/,/g,'')); return isNaN(n) ? 0 : n; }
  function f(n, d){ return n.toLocaleString('en-US', {minimumFractionDigits:d, maximumFractionDigits:d}); }
  function calc(){
    var cur = $('mm-cur').value, dec = (cur === 'JOD' || cur === 'KWD') ? 3 : 2;
    var s = Math.max(0, v('mm-spend')), imp = Math.max(0, v('mm-imp')), clk = Math.max(0, v('mm-clk')), conv = Math.max(0, v('mm-conv'));
    $('mm-cpm').textContent = imp > 0 ? f((s / imp) * 1000, dec) + ' ' + cur : '—';
    $('mm-ctr').textContent = imp > 0 ? f((clk / imp) * 100, 2) + '%' : '—';
    $('mm-cpc').textContent = clk > 0 ? f(s / clk, dec) + ' ' + cur : '—';
    $('mm-cvr').textContent = clk > 0 ? f((conv / clk) * 100, 2) + '%' : '—';
    $('mm-cpa').textContent = conv > 0 ? f(s / conv, dec) + ' ' + cur : '—';
  }
  ['mm-spend','mm-imp','mm-clk','mm-conv'].forEach(function(id){ $(id).addEventListener('input', calc); });
  $('mm-cur').addEventListener('change', calc);
  calc();
})();
</script>
@endpush
