@php
// Free ad tool · /tools/roas-calculator (D-90 set). Browser only; amounts in the code the visitor picks, no conversion.
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/roas-calculator',
    ['حاسبة العائد على الإنفاق الإعلاني (ROAS) | AdsRanger', 'ROAS Calculator for Ad Campaigns | AdsRanger'],
    ['احسب العائد على الإنفاق الإعلاني ونقطة التعادل والربح من أرقامك أنت. مجانًا ودون تسجيل، ولا نحفظ شيئًا.',
        'Work out return on ad spend, break-even ROAS and profit from your own numbers. Free, no sign-up, nothing stored.']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
$t = $ar ? ['cur' => 'العملة', 'spend' => 'الإنفاق الإعلاني', 'rev' => 'الإيرادات من الإعلانات', 'margin' => 'هامش الربح الإجمالي (%)', 'margin_hint' => 'اختياري؛ لحساب نقطة التعادل والربح.',
    'roas' => 'العائد على الإنفاق (ROAS)', 'be' => 'العائد اللازم للتعادل', 'profit' => 'الربح بعد الإعلانات', 'err' => 'أدخل رقمًا أكبر من 0.', 'note' => 'العائد = الإيرادات ÷ الإنفاق. والتعادل = 1 ÷ هامش الربح.']
    : ['cur' => 'Currency', 'spend' => 'Ad spend', 'rev' => 'Revenue from ads', 'margin' => 'Gross margin (%)', 'margin_hint' => 'Optional; for break-even and profit.',
    'roas' => 'Return on ad spend (ROAS)', 'be' => 'Break-even ROAS', 'profit' => 'Profit after ads', 'err' => 'Enter a number above 0.', 'note' => 'ROAS = revenue ÷ spend. Break-even = 1 ÷ margin.'];
$curs = ['USD', 'SAR', 'AED', 'JOD', 'KWD', 'QAR', 'EGP'];
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'حاسبة العائد على الإنفاق الإعلاني' : 'ROAS calculator' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'أدخل ما أنفقته على الإعلانات وما جلبته من إيرادات، واعرف العائد ونقطة التعادل.' : 'Enter what you spent on ads and the revenue they brought, and see your return and break-even point.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" id="rs-form" onsubmit="return false" novalidate>
        <label for="rs-cur">{{ $t['cur'] }}</label>
        <select id="rs-cur">@foreach ($curs as $c)<option>{{ $c }}</option>@endforeach</select>
        <label for="rs-spend">{{ $t['spend'] }}</label>
        <input id="rs-spend" type="text" inputmode="decimal" dir="ltr" value="1000">
        <label for="rs-rev">{{ $t['rev'] }}</label>
        <input id="rs-rev" type="text" inputmode="decimal" dir="ltr" value="4200">
        <label for="rs-margin">{{ $t['margin'] }}</label>
        <input id="rs-margin" type="text" inputmode="decimal" dir="ltr" value="40" aria-describedby="rs-mh">
        <p class="tl-hint" id="rs-mh">{{ $t['margin_hint'] }}</p>
        <p class="tl-err" id="rs-err" hidden>{{ $t['err'] }}</p>
    </form>
    <div class="tl-out" aria-live="polite">
        <ul class="tl-kv">
            <li>{{ $t['roas'] }}<strong id="rs-roas" dir="ltr"></strong></li>
            <li>{{ $t['be'] }}<strong id="rs-be" dir="ltr"></strong></li>
            <li>{{ $t['profit'] }}<strong id="rs-profit" dir="ltr"></strong></li>
        </ul>
        <p class="muted" style="margin-top:12px">{{ $t['note'] }}</p>
        <p class="muted">{{ $privacy }}</p>
    </div>
</section>
<p><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الانتظار' : 'Join the waitlist' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'roas-calculator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function (id) { return document.getElementById(id); };
  function n(id) { var v = $(id).value.trim().replace(/,/g, ''); return v === '' || isNaN(v) ? null : Number(v); }
  function fmt(x, d) { return x.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }
  function calc() {
    var s = n('rs-spend'), r = n('rs-rev'), m = n('rs-margin'), cur = $('rs-cur').value, bad = !(s > 0) || r === null || r < 0;
    $('rs-err').hidden = !bad;
    if (bad) { ['rs-roas', 'rs-be', 'rs-profit'].forEach(function (i) { $(i).textContent = '—'; }); return; }
    $('rs-roas').textContent = fmt(r / s, 2) + '×';
    $('rs-be').textContent = m > 0 ? fmt(100 / m, 2) + '×' : '—';
    $('rs-profit').textContent = m > 0 ? fmt(r * m / 100 - s, 2) + ' ' + cur : '—';
  }
  ['rs-spend', 'rs-rev', 'rs-margin'].forEach(function (i) { $(i).addEventListener('input', calc); });
  $('rs-cur').addEventListener('change', calc);
  calc();
})();
</script>
@endpush
