@php
extract(\App\Support\ToolPage::head($lang ?? 'ar', '/tools/agency-fee-vat-calculator',
    ['حاسبة أتعاب الوكالة والضريبة | AdsRanger', 'Agency Fee & MENA VAT Calculator | AdsRanger'],
    ['قارن بين الأتعاب الشهرية الثابتة ونسبة إدارة الإنفاق الإعلاني واحسب صافي هامش الوكالة وضريبة القيمة المضافة للسعودية والأردن والإمارات ومصر.',
        'Compare fixed agency retainer vs % of ad spend, media float vs net agency margin, and MENA VAT (KSA 15%, Jordan 16%, UAE 5%, Egypt 14%).']));
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $lang);
@endphp
@extends('layouts.public')
@push('styles')
@include('public.tools.partials.styles')
@endpush
@section('hero')
<h1>{{ $ar ? 'حاسبة أتعاب الوكالة وضريبة القيمة المضافة' : 'Agency Fee, Margin & MENA VAT Calculator' }}</h1>
<p class="muted" style="font-size:17px">{{ $ar ? 'احسب أتعاب إدارة الحملات وصافي هامش الوكالة والفاتورة الضريبية (السعودية 15%، الأردن 16% ضريبة مبيعات، الإمارات 5%، مصر 14%؛ نسب للتخطيط فقط).' : 'Model retainer vs % of spend, client card vs agency media float, net agency margin, and MENA VAT.' }}</p>
@endsection
@section('content')
<section class="tl">
    <form class="card" onsubmit="return false" novalidate>
        <label for="af-mkt">{{ $ar ? 'الدولة ونسبة الضريبة' : 'Market & VAT preset' }}</label>
        <select id="af-mkt">
            <option value="15|SAR">{{ $ar ? 'السعودية — 15% (SAR)' : 'Saudi Arabia — 15% VAT (SAR)' }}</option>
            <option value="16|JOD">{{ $ar ? 'الأردن — 16% (JOD)' : 'Jordan — 16% Tax (JOD)' }}</option>
            <option value="5|AED">{{ $ar ? 'الإمارات — 5% (AED)' : 'UAE — 5% VAT (AED)' }}</option>
            <option value="14|EGP">{{ $ar ? 'مصر — 14% (EGP)' : 'Egypt — 14% VAT (EGP)' }}</option>
        </select>

        <label for="af-spend">{{ $ar ? 'ميزانية الإعلانات الشهرية للعميل' : 'Monthly client ad spend' }}</label>
        <input id="af-spend" type="text" inputmode="decimal" dir="ltr" value="20000">

        <label for="af-ret">{{ $ar ? 'أتعاب الإدارة الشهرية الثابتة (Retainer)' : 'Base monthly retainer' }}</label>
        <input id="af-ret" type="text" inputmode="decimal" dir="ltr" value="2500">

        <label for="af-pct">{{ $ar ? 'نسبة عمولة الإدارة من الإنفاق (%)' : 'Management fee (% of ad spend)' }}</label>
        <input id="af-pct" type="text" inputmode="decimal" dir="ltr" value="10">

        <label for="af-cost">{{ $ar ? 'تكلفة فريق الوكالة والتشغيل لهذا العميل' : 'Internal agency delivery cost' }}</label>
        <input id="af-cost" type="text" inputmode="decimal" dir="ltr" value="1600">

        <label for="af-float">{{ $ar ? 'طريقة سداد ميزانية المنصات' : 'Media spend billing mode' }}</label>
        <select id="af-float">
            <option value="client">{{ $ar ? 'بطاقة العميل مباشرة (فوترة الأتعاب فقط)' : 'Client pays platforms directly (invoice fee only)' }}</option>
            <option value="agency">{{ $ar ? 'بطاقة الوكالة (تمرير الإنفاق الإعلاني في الفاتورة)' : 'Agency fronts media spend (pass-through invoice)' }}</option>
        </select>
    </form>

    <div class="tl-out" aria-live="polite">
        <ul class="tl-kv">
            <li><span>{{ $ar ? 'إجمالي إيراد أتعاب الوكالة (قبل الضريبة)' : 'Total agency fee revenue (ex-VAT)' }}</span><strong id="af-fee" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'صافي ربح الوكالة وهامشها الفعلي' : 'Net agency profit & real margin' }}</span><strong id="af-margin" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'التدفق النقدي الممرر للمنصات (Media Float)' : 'Media float exposure' }}</span><strong id="af-media" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'قيمة ضريبة القيمة المضافة' : 'VAT amount' }}</span><strong id="af-vat" dir="ltr"></strong></li>
            <li><span>{{ $ar ? 'إجمالي الفاتورة المستحقة على العميل' : 'Total client invoice (inc. VAT)' }}</span><strong id="af-tot" dir="ltr"></strong></li>
        </ul>
        <p class="muted" style="margin-top:14px">{{ $privacy }}</p>
    </div>
</section>
<p style="margin-top:20px"><a class="btn" href="{{ $u('/waitlist') }}?type=agency">{{ $ar ? 'انضم إلى قائمة الإطلاق' : 'Join the launch list' }}</a></p>
@include('public.tools.partials.tool-guide-faq', ['toolSlug' => 'agency-fee-vat-calculator', 'ar' => $ar])
@endsection
@push('scripts')
<script>
(function(){
  var $ = function(id){ return document.getElementById(id); };
  function v(id){ var n = parseFloat($(id).value.replace(/,/g,'')); return isNaN(n) ? 0 : n; }
  function f(n, d){ return n.toLocaleString('en-US', {minimumFractionDigits:d, maximumFractionDigits:d}); }
  function calc(){
    var parts = $('af-mkt').value.split('|'), vatRate = parseFloat(parts[0]) / 100, cur = parts[1];
    var dec = cur === 'JOD' ? 3 : 2;
    var spend = Math.max(0, v('af-spend')), ret = Math.max(0, v('af-ret')), pct = Math.max(0, v('af-pct')) / 100, cost = Math.max(0, v('af-cost'));
    var pass = $('af-float').value === 'agency';

    var feeRev = ret + (spend * pct);
    var net = feeRev - cost;
    var marginPct = feeRev > 0 ? (net / feeRev) * 100 : 0;
    var taxableSub = pass ? (feeRev + spend) : feeRev;
    var vat = taxableSub * vatRate;
    var total = taxableSub + vat;

    $('af-fee').textContent = f(feeRev, dec) + ' ' + cur;
    $('af-margin').textContent = f(net, dec) + ' ' + cur + ' (' + marginPct.toFixed(1) + '%)';
    $('af-media').textContent = f(pass ? spend : 0, dec) + ' ' + cur;
    $('af-vat').textContent = f(vat, dec) + ' ' + cur;
    $('af-tot').textContent = f(total, dec) + ' ' + cur;
  }
  ['af-spend','af-ret','af-pct','af-cost'].forEach(function(id){ $(id).addEventListener('input', calc); });
  ['af-mkt','af-float'].forEach(function(id){ $(id).addEventListener('change', calc); });
  calc();
})();
</script>
@endpush
